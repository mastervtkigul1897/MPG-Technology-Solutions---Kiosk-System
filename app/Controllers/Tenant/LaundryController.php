<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\LaundrySchema;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class LaundryController
{
    private static ?bool $hasLaundryInventoryImagePath = null;

    private static ?bool $hasUsersDayRateColumn = null;
    private static ?bool $hasUsersOvertimeRateColumn = null;
    private static ?bool $hasUsersWorkDaysCsvColumn = null;
    private static ?bool $hasLaundryOrdersReferenceCode = null;
    private static ?bool $hasLaundryOrdersDiscountPercentage = null;
    private static ?bool $hasLaundryOrdersDiscountAmount = null;
    private static ?bool $hasLaundryOrdersAmountTendered = null;
    private static ?bool $hasLaundryOrdersChangeAmount = null;
    private const FREE_LIMIT_WASHERS = 1;
    private const FREE_LIMIT_DRYERS = 1;

    private static function hasUsersDayRateColumn(PDO $pdo): bool
    {
        if (self::$hasUsersDayRateColumn !== null) {
            return self::$hasUsersDayRateColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'day_rate'");
            self::$hasUsersDayRateColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersDayRateColumn = false;
        }

        return self::$hasUsersDayRateColumn;
    }

    private static function hasUsersOvertimeRateColumn(PDO $pdo): bool
    {
        if (self::$hasUsersOvertimeRateColumn !== null) {
            return self::$hasUsersOvertimeRateColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'overtime_rate_per_hour'");
            self::$hasUsersOvertimeRateColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersOvertimeRateColumn = false;
        }

        return self::$hasUsersOvertimeRateColumn;
    }

    private static function hasUsersWorkDaysCsvColumn(PDO $pdo): bool
    {
        if (self::$hasUsersWorkDaysCsvColumn !== null) {
            return self::$hasUsersWorkDaysCsvColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'work_days_csv'");
            self::$hasUsersWorkDaysCsvColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersWorkDaysCsvColumn = false;
        }

        return self::$hasUsersWorkDaysCsvColumn;
    }

    public function salesIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $laundryStatusTrackingEnabled = $this->isLaundryStatusTrackingEnabled($pdo, $tenantId);
        $trackMachineMovementEnabled = $this->isTrackMachineMovementEnabled($pdo, $tenantId);
        $defaultDryingMinutes = $this->getBranchDefaultDryingMinutes($pdo, $tenantId);
        if ($laundryStatusTrackingEnabled && $trackMachineMovementEnabled) {
            $this->processTrackMachineMovementTimers($pdo, $tenantId);
        }
        $editableOrderDate = $this->isEditableOrderDateEnabled($pdo, $tenantId);
        $transactionsScope = strtolower(trim((string) $request->input('tx_scope', 'today')));
        if (! in_array($transactionsScope, ['today', 'all'], true)) {
            $transactionsScope = 'today';
        }
        $transactionsMode = strtolower(trim((string) $request->input('tx_mode', 'paged')));
        if (! in_array($transactionsMode, ['paged', 'all'], true)) {
            $transactionsMode = 'paged';
        }
        $transactionsPerPage = 25;
        $transactionsPage = max(1, (int) $request->input('page', 1));
        $statusView = strtolower(trim((string) $request->input('tx_status', '')));
        if ($statusView === '') {
            // Backward-compatible support for older tx_payment links.
            $statusView = strtolower(trim((string) $request->input('tx_payment', 'all')));
        }
        if (! in_array($statusView, ['all', 'pending', 'washing_drying', 'paid', 'unpaid', 'void'], true)) {
            $statusView = 'all';
        }
        $statusFilterSql = match ($statusView) {
            'pending' => ' AND COALESCE(o.is_void, 0) = 0 AND o.status = "pending"',
            'washing_drying' => ' AND COALESCE(o.is_void, 0) = 0 AND o.status IN ("washing_drying", "running")',
            'paid' => ' AND COALESCE(o.is_void, 0) = 0 AND o.status <> "void" AND o.payment_status = "paid"',
            'unpaid' => ' AND COALESCE(o.is_void, 0) = 0 AND o.status <> "void" AND o.payment_status <> "paid"',
            'void' => ' AND (COALESCE(o.is_void, 0) = 1 OR o.status = "void")',
            default => '',
        };

        $customers = $pdo->prepare(
            'SELECT c.id, c.name, COALESCE(cp.points_balance, 0) AS rewards_balance
             FROM laundry_customers c
             LEFT JOIN laundry_customer_points cp ON cp.customer_id = c.id AND cp.tenant_id = c.tenant_id
             WHERE c.tenant_id = ?
             ORDER BY c.name'
        );
        $customers->execute([$tenantId]);
        $freeCustomerLocked = Auth::isTenantFreePlanRestricted(Auth::user());
        $customerRows = $freeCustomerLocked ? [] : $customers->fetchAll(PDO::FETCH_ASSOC);

        $machinesSt = $pdo->prepare(
            'SELECT id, machine_label, machine_kind, machine_type, credit_required, credit_balance, status
             FROM laundry_machines
             WHERE tenant_id = ? AND status = "available"
             ORDER BY machine_kind ASC,
                      CASE WHEN credit_required = 1 AND credit_balance <= 0 THEN 1 ELSE 0 END ASC,
                      machine_label ASC, id ASC'
        );
        $machinesSt->execute([$tenantId]);
        $machineRows = $this->filterFreeModeMachineRows($machinesSt->fetchAll(PDO::FETCH_ASSOC));

        $showItemInSelect = $this->hasColumn($pdo, 'laundry_inventory_items', 'show_item_in')
            ? 'COALESCE(show_item_in, "both") AS show_item_in'
            : '"both" AS show_item_in';
        $invByCategory = $pdo->prepare(
            'SELECT id, name, category, unit_cost, stock_quantity, '.$showItemInSelect.'
             FROM laundry_inventory_items
             WHERE tenant_id = ? AND category IN ("detergent", "fabcon", "bleach", "other")
             ORDER BY category ASC, name ASC'
        );
        $invByCategory->execute([$tenantId]);
        $detergentItems = [];
        $fabconItems = [];
        $bleachItems = [];
        $otherItems = [];
        foreach ($invByCategory->fetchAll(PDO::FETCH_ASSOC) as $inv) {
            $cat = (string) ($inv['category'] ?? '');
            if ($cat === 'detergent') {
                $detergentItems[] = $inv;
            } elseif ($cat === 'fabcon') {
                $fabconItems[] = $inv;
            } elseif ($cat === 'bleach') {
                $bleachItems[] = $inv;
            } elseif ($cat === 'other') {
                $otherItems[] = $inv;
            }
        }

        $ordersRows = [];
        $transactionsTotal = 0;
        $transactionsTotalPages = 1;
        if ($transactionsScope === 'all') {
            $countSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_orders o WHERE o.tenant_id = ?'.$statusFilterSql);
            $countSt->execute([$tenantId]);
            $transactionsTotal = max(0, (int) $countSt->fetchColumn());
            if ($transactionsMode === 'all') {
                $orders = $pdo->prepare(
                    'SELECT o.*, c.name AS customer_name,
                            m.machine_label AS legacy_machine_label, m.machine_kind AS legacy_machine_kind,
                            mw.machine_label AS washer_machine_label, mw.machine_kind AS washer_kind,
                            md.machine_label AS dryer_machine_label, md.machine_kind AS dryer_kind,
                            ot.label AS order_type_label, ot.service_kind AS order_type_service_kind
                     FROM laundry_orders o
                     LEFT JOIN laundry_customers c ON c.id = o.customer_id
                     LEFT JOIN laundry_machines m ON m.id = o.machine_id
                     LEFT JOIN laundry_machines mw ON mw.id = o.washer_machine_id
                     LEFT JOIN laundry_machines md ON md.id = o.dryer_machine_id
                     LEFT JOIN laundry_order_types ot ON ot.tenant_id = o.tenant_id AND ot.code = o.order_type
                     WHERE o.tenant_id = ?'.$statusFilterSql.'
                     ORDER BY o.id DESC'
                );
                $orders->execute([$tenantId]);
                $ordersRows = $orders->fetchAll(PDO::FETCH_ASSOC);
                $transactionsTotalPages = 1;
                $transactionsPage = 1;
            } else {
                $transactionsTotalPages = max(1, (int) ceil($transactionsTotal / $transactionsPerPage));
                if ($transactionsPage > $transactionsTotalPages) {
                    $transactionsPage = $transactionsTotalPages;
                }
                $offset = ($transactionsPage - 1) * $transactionsPerPage;
                $orders = $pdo->prepare(
                    'SELECT o.*, c.name AS customer_name,
                            m.machine_label AS legacy_machine_label, m.machine_kind AS legacy_machine_kind,
                            mw.machine_label AS washer_machine_label, mw.machine_kind AS washer_kind,
                            md.machine_label AS dryer_machine_label, md.machine_kind AS dryer_kind,
                            ot.label AS order_type_label, ot.service_kind AS order_type_service_kind
                     FROM laundry_orders o
                     LEFT JOIN laundry_customers c ON c.id = o.customer_id
                     LEFT JOIN laundry_machines m ON m.id = o.machine_id
                     LEFT JOIN laundry_machines mw ON mw.id = o.washer_machine_id
                     LEFT JOIN laundry_machines md ON md.id = o.dryer_machine_id
                     LEFT JOIN laundry_order_types ot ON ot.tenant_id = o.tenant_id AND ot.code = o.order_type
                     WHERE o.tenant_id = ?'.$statusFilterSql.'
                     ORDER BY o.id DESC
                     LIMIT ? OFFSET ?'
                );
                $orders->bindValue(1, $tenantId, PDO::PARAM_INT);
                $orders->bindValue(2, $transactionsPerPage, PDO::PARAM_INT);
                $orders->bindValue(3, $offset, PDO::PARAM_INT);
                $orders->execute();
                $ordersRows = $orders->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $orders = $pdo->prepare(
                'SELECT o.*, c.name AS customer_name,
                        m.machine_label AS legacy_machine_label, m.machine_kind AS legacy_machine_kind,
                        mw.machine_label AS washer_machine_label, mw.machine_kind AS washer_kind,
                        md.machine_label AS dryer_machine_label, md.machine_kind AS dryer_kind,
                        ot.label AS order_type_label, ot.service_kind AS order_type_service_kind
                 FROM laundry_orders o
                 LEFT JOIN laundry_customers c ON c.id = o.customer_id
                 LEFT JOIN laundry_machines m ON m.id = o.machine_id
                 LEFT JOIN laundry_machines mw ON mw.id = o.washer_machine_id
                 LEFT JOIN laundry_machines md ON md.id = o.dryer_machine_id
                 LEFT JOIN laundry_order_types ot ON ot.tenant_id = o.tenant_id AND ot.code = o.order_type
                 WHERE o.tenant_id = ?
                   AND (
                       (
                           COALESCE(o.is_void, 0) = 0
                           AND o.status <> "void"
                           AND (
                               o.status IN ("pending", "washing_drying", "open_ticket", "running")
                               OR (o.status = "paid" AND DATE(o.created_at) = CURDATE())
                           )
                       )
                       OR (COALESCE(o.is_void, 0) = 1 AND DATE(o.created_at) = CURDATE())
                   )
                 ORDER BY o.id DESC
                 LIMIT 200'
            );
            $orders->execute([$tenantId]);
            $ordersRows = $orders->fetchAll(PDO::FETCH_ASSOC);
            $todayDate = date('Y-m-d');
            $ordersRows = array_values(array_filter($ordersRows, static function (array $row) use ($todayDate, $statusView): bool {
                $status = (string) ($row['status'] ?? '');
                $isVoid = ! empty($row['is_void']) || $status === 'void';
                $isPending = $status === 'pending';
                $isWashingDrying = in_array($status, ['washing_drying', 'running'], true);
                $isPaid = (string) ($row['payment_status'] ?? 'unpaid') === 'paid';
                if ($statusView === 'pending' && ($isVoid || ! $isPending)) {
                    return false;
                }
                if ($statusView === 'washing_drying' && ($isVoid || ! $isWashingDrying)) {
                    return false;
                }
                if ($statusView === 'paid' && ($isVoid || ! $isPaid)) {
                    return false;
                }
                if ($statusView === 'unpaid' && ($isVoid || $isPaid)) {
                    return false;
                }
                if ($statusView === 'void' && ! $isVoid) {
                    return false;
                }
                $isTerminal = $status === 'paid';
                if (! $isVoid && ! $isTerminal) {
                    return true;
                }

                $createdAt = trim((string) ($row['created_at'] ?? ''));
                if ($createdAt === '') {
                    return false;
                }
                $createdTs = strtotime($createdAt);
                if ($createdTs === false) {
                    return false;
                }

                return date('Y-m-d', $createdTs) === $todayDate;
            }));
            $transactionsTotal = count($ordersRows);
        }

        return view_page('Transactions', 'tenant.laundry.sales', [
            'customers' => $customerRows,
            'machines' => $machineRows,
            'detergent_items' => $detergentItems,
            'fabcon_items' => $fabconItems,
            'bleach_items' => $bleachItems,
            'other_items' => $otherItems,
            'orders' => $ordersRows,
            'order_types' => $this->fetchActiveOrderTypes($pdo, $tenantId),
            'reward_config' => $this->fetchRewardConfigForRedemption($pdo, $tenantId),
            'machine_assignment_enabled' => $this->isMachineAssignmentEnabled($pdo, $tenantId),
            'laundry_status_tracking_enabled' => $laundryStatusTrackingEnabled,
            'track_machine_movement_enabled' => $trackMachineMovementEnabled,
            'default_drying_minutes' => $defaultDryingMinutes,
            'editable_order_date' => $editableOrderDate,
            'transactions_scope' => $transactionsScope,
            'transactions_mode' => $transactionsMode,
            'transactions_page' => $transactionsPage,
            'transactions_per_page' => $transactionsPerPage,
            'transactions_total' => $transactionsTotal,
            'transactions_total_pages' => $transactionsTotalPages,
            'transactions_status_filter' => $statusView,
        ]);
    }

    public function staffPortalIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $customers = $pdo->prepare(
            'SELECT c.id, c.name, COALESCE(cp.points_balance, 0) AS rewards_balance, COUNT(o.id) AS transaction_count
             FROM laundry_customers c
             LEFT JOIN laundry_orders o ON o.tenant_id = c.tenant_id AND o.customer_id = c.id
             LEFT JOIN laundry_customer_points cp ON cp.customer_id = c.id AND cp.tenant_id = c.tenant_id
             WHERE c.tenant_id = ?
             GROUP BY c.id, c.name, cp.points_balance
             ORDER BY transaction_count DESC, c.name ASC
             LIMIT 200'
        );
        $customers->execute([$tenantId]);
        $freeCustomerLocked = Auth::isTenantFreePlanRestricted(Auth::user());
        $customerRows = $freeCustomerLocked ? [] : $customers->fetchAll(PDO::FETCH_ASSOC);

        $machinesSt = $pdo->prepare(
            'SELECT id, machine_label, machine_kind, machine_type, credit_required, credit_balance, status
             FROM laundry_machines
             WHERE tenant_id = ? AND status = "available"
             ORDER BY machine_kind ASC,
                      CASE WHEN credit_required = 1 AND credit_balance <= 0 THEN 1 ELSE 0 END ASC,
                      machine_label ASC, id ASC'
        );
        $machinesSt->execute([$tenantId]);
        $machines = $this->filterFreeModeMachineRows($machinesSt->fetchAll(PDO::FETCH_ASSOC));

        $invSelectImage = $this->hasLaundryInventoryImagePath($pdo) ? ', image_path' : '';
        $showItemInSelect = $this->hasColumn($pdo, 'laundry_inventory_items', 'show_item_in')
            ? ', COALESCE(show_item_in, "both") AS show_item_in'
            : ', "both" AS show_item_in';
        $invByCategory = $pdo->prepare(
            'SELECT id, name, category, stock_quantity, low_stock_threshold, unit_cost'.$invSelectImage.$showItemInSelect.'
             FROM laundry_inventory_items
             WHERE tenant_id = ? AND category IN ("detergent", "fabcon", "bleach", "other")
             ORDER BY category ASC, name ASC'
        );
        $invByCategory->execute([$tenantId]);
        $detergentItems = [];
        $fabconItems = [];
        $bleachItems = [];
        $otherItems = [];
        foreach ($invByCategory->fetchAll(PDO::FETCH_ASSOC) as $inv) {
            $cat = (string) ($inv['category'] ?? '');
            if ($cat === 'detergent') {
                $detergentItems[] = $inv;
            } elseif ($cat === 'fabcon') {
                $fabconItems[] = $inv;
            } elseif ($cat === 'bleach') {
                $bleachItems[] = $inv;
            } elseif ($cat === 'other') {
                $otherItems[] = $inv;
            }
        }

        $clockOpenSt = $pdo->prepare(
            'SELECT id
             FROM laundry_time_logs
             WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = ? AND clock_out_at IS NULL
             LIMIT 1'
        );
        $clockOpenSt->execute([$tenantId, (int) (Auth::user()['id'] ?? 0), date('Y-m-d')]);
        $nextOrderId = 1;
        try {
            $nextOrderId = 1 + (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM laundry_orders WHERE tenant_id = '.(int) $tenantId)->fetchColumn();
        } catch (\Throwable) {
            $nextOrderId = 1;
        }
        $referencePreview = $this->generateOrderReferenceCandidate($nextOrderId);

        return view_page('Staff Kiosk Portal', 'tenant.laundry.staff-portal', [
            'customers' => $customerRows,
            'machines' => $machines,
            'order_types' => $this->fetchActiveOrderTypes($pdo, $tenantId),
            'reward_config' => $this->fetchRewardConfigForRedemption($pdo, $tenantId),
            'detergent_items' => $detergentItems,
            'fabcon_items' => $fabconItems,
            'bleach_items' => $bleachItems,
            'other_items' => $otherItems,
            'is_clocked_in' => $clockOpenSt->fetch(PDO::FETCH_ASSOC) !== false,
            'machine_assignment_enabled' => $this->isMachineAssignmentEnabled($pdo, $tenantId),
            'laundry_status_tracking_enabled' => $this->isLaundryStatusTrackingEnabled($pdo, $tenantId),
            'track_machine_movement_enabled' => $this->isTrackMachineMovementEnabled($pdo, $tenantId),
            'enable_bluetooth_print' => $this->isBranchBluetoothPrintEnabled($pdo, $tenantId),
            'next_transaction_id' => $nextOrderId,
            'reference_preview' => $referencePreview,
            'free_customer_locked' => $freeCustomerLocked,
            'track_gasul_usage' => $this->isBranchTrackGasulUsageEnabled($pdo, $tenantId),
            'kiosk_automation_settings' => $this->getBranchKioskAutomationSettings($pdo, $tenantId),
        ]);
    }

    public function salesStore(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $userId = (int) (Auth::user()['id'] ?? 0);
        $origin = strtolower(trim((string) $request->input('origin', '')));
        $redirectRoute = $origin === 'staff_portal'
            ? 'tenant.staff-portal.index'
            : 'tenant.laundry-sales.index';
        $updateWorkflow = (string) $request->input('update_laundry_status_workflow', '') === '1';
        $updateOrderDateEdit = (string) $request->input('update_editable_order_date', '') === '1';
        $updateKioskAutomation = (string) $request->input('update_kiosk_automation', '') === '1';
        if ($updateWorkflow || $updateOrderDateEdit || $updateKioskAutomation) {
            $actor = Auth::user();
            if (($actor['role'] ?? '') !== 'tenant_admin') {
                session_flash('errors', ['Only the store owner can update this setting.']);
                return redirect(route('tenant.laundry-sales.index'));
            }
            if ($updateWorkflow) {
                $wasWorkflowEnabled = $this->isLaundryStatusTrackingEnabled($pdo, $tenantId);
                $enabled = $request->boolean('laundry_status_tracking_enabled');
                $this->persistLaundryStatusTrackingConfig($pdo, $tenantId, $enabled);
                $trackMachineMovement = $request->boolean('track_machine_movement');
                $defaultDryingMinutesInput = trim((string) $request->input('default_drying_minutes', ''));
                if (! $enabled) {
                    // When workflow is OFF, keep both movement modes OFF to avoid conflicting states.
                    $trackMachineMovement = false;
                    $this->persistTrackMachineMovementConfig($pdo, $tenantId, false);
                    $this->persistMachineAssignmentConfig($pdo, $tenantId, false);
                }
                if ($enabled && ! $wasWorkflowEnabled) {
                    // When workflow is newly enabled, default to Automatic mode.
                    $trackMachineMovement = true;
                }
                if ($trackMachineMovement && $defaultDryingMinutesInput === '') {
                    session_flash('errors', ['Default drying minutes is required when machine movement tracking is enabled.']);
                    return redirect(route($redirectRoute));
                }
                $defaultDryingMinutes = $defaultDryingMinutesInput === ''
                    ? null
                    : max(1, (int) $defaultDryingMinutesInput);
                if ($enabled) {
                    $this->persistTrackMachineMovementConfig($pdo, $tenantId, $trackMachineMovement);
                }
                if ($enabled && $trackMachineMovement) {
                    // Automatic and manual modes are mutually exclusive.
                    $this->persistMachineAssignmentConfig($pdo, $tenantId, false);
                }
                $this->persistBranchDefaultDryingMinutes($pdo, $tenantId, $defaultDryingMinutes);
            }
            if ($updateOrderDateEdit) {
                $enabled = $request->boolean('editable_order_date');
                $this->persistEditableOrderDateConfig($pdo, $tenantId, $enabled);
            }
            if ($updateKioskAutomation) {
                $this->persistBranchKioskAutomationSettings($pdo, $tenantId, $this->parseKioskAutomationSettingsFromRequest($request));
            }
            session_flash('success', 'Sales settings updated.');
            return redirect(route($redirectRoute));
        }
        $orderMode = strtolower(trim((string) $request->input('order_mode', 'drop_off')));
        if (! in_array($orderMode, ['drop_off', 'self_service', 'add_on_only'], true)) {
            $orderMode = 'drop_off';
        }
        $effectiveOrderLinePrice = static function (array $orderTypeDef): float {
            $basePrice = max(0.0, (float) ($orderTypeDef['price_per_load'] ?? 0));
            $foldServiceAmount = max(0.0, (float) ($orderTypeDef['fold_service_amount'] ?? $basePrice));
            $code = strtolower(trim((string) ($orderTypeDef['code'] ?? '')));
            if ($code === 'free_fold') {
                return 0.0;
            }
            if ($code === 'fold_with_price') {
                return $foldServiceAmount;
            }
            return $basePrice;
        };

        $serviceMode = strtolower(trim((string) $request->input('service_mode', 'regular')));
        if (! in_array($serviceMode, ['regular', 'free', 'reward'], true)) {
            $serviceMode = 'regular';
        }
        if ($orderMode === 'self_service' || $orderMode === 'add_on_only') {
            $serviceMode = 'regular';
        }
        if ($request->boolean('reward_redemption')) {
            $serviceMode = 'reward';
        }
        if ($orderMode === 'self_service' || $orderMode === 'add_on_only') {
            $serviceMode = 'regular';
        }
        $customerSelection = strtolower(trim((string) $request->input('customer_selection', '')));
        $customerId = (int) $request->input('customer_id', 0);
        $isFreeCustomerLocked = Auth::isTenantFreePlanRestricted(Auth::user());
        if ($orderMode === 'self_service' || $orderMode === 'add_on_only') {
            $customerSelection = 'walk_in';
            $customerId = 0;
        } elseif ($isFreeCustomerLocked) {
            $customerSelection = 'walk_in';
            $customerId = 0;
        } elseif (! in_array($customerSelection, ['saved', 'walk_in'], true)) {
            session_flash('errors', ['Customer is required. Select a saved customer or choose Walk-in customer.']);

            return redirect(route($redirectRoute));
        }
        if ($customerSelection === 'saved' && $customerId < 1) {
            session_flash('errors', ['Select a valid saved customer.']);

            return redirect(route($redirectRoute));
        }
        if ($customerSelection === 'walk_in') {
            $customerId = 0;
        }
        $paymentTiming = strtolower(trim((string) $request->input('payment_timing', 'pay_later')));
        if (! in_array($paymentTiming, ['pay_now', 'pay_later'], true)) {
            $paymentTiming = 'pay_later';
        }
        $requestedPaymentMethod = strtolower(trim((string) $request->input('payment_method', '')));
        if (! in_array($requestedPaymentMethod, ['cash', 'gcash', 'paymaya', 'online_banking', 'qr_payment', 'card', 'split_payment', 'pending'], true)) {
            $requestedPaymentMethod = '';
        }
        $amountTendered = max(0.0, (float) $request->input('amount_tendered', 0));
        $discountPercentage = (float) $request->input('discount_percentage', 0);
        $discountPercentage = max(0.0, min(100.0, $discountPercentage));
        $splitCashAmount = round(max(0.0, (float) $request->input('split_cash_amount', 0)), 4);
        $splitOnlineAmount = round(max(0.0, (float) $request->input('split_online_amount', 0)), 4);
        $splitOnlineMethod = strtolower(trim((string) $request->input('split_online_method', '')));
        if (! in_array($splitOnlineMethod, ['gcash', 'paymaya', 'online_banking', 'qr_payment', 'card'], true)) {
            $splitOnlineMethod = '';
        }
        $enableBluetoothPrint = $request->boolean('enable_bluetooth_print');
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            $enableBluetoothPrint = false;
        }
        // IMPORTANT: Always use branch config as source of truth.
        // Do not override status workflow mode from request input.
        $trackLaundryStatus = $this->isLaundryStatusTrackingEnabled($pdo, $tenantId);
        $trackMachineMovementEnabled = $this->isTrackMachineMovementEnabled($pdo, $tenantId);
        $rewardConfig = null;
        if ($serviceMode === 'reward') {
            if ($customerId < 1) {
                session_flash('errors', ['Select a customer before using a reward.']);

                return redirect(route($redirectRoute));
            }
            $rewardConfig = $this->fetchRewardConfigForRedemption($pdo, $tenantId);
            if ($rewardConfig === null) {
                session_flash('errors', ['No active reward service is configured.']);

                return redirect(route($redirectRoute));
            }
            $orderTypeCodeInput = (string) ($rewardConfig['reward_order_type_code'] ?? '');
        } else {
            $orderTypeCodeInput = trim((string) $request->input('order_type', ''));
        }
        $selfServiceLines = [];
        $dropOffLines = [];
        if ($orderMode === 'self_service') {
            $rawLines = trim((string) $request->input('self_service_lines', ''));
            if ($rawLines === '') {
                session_flash('errors', ['Tap at least one order type for Self Service.']);

                return redirect(route($redirectRoute));
            }
            try {
                $decoded = json_decode($rawLines, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $decoded = null;
            }
            if (! is_array($decoded) || $decoded === []) {
                session_flash('errors', ['Invalid Self Service order lines.']);

                return redirect(route($redirectRoute));
            }
            foreach ($decoded as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $code = trim((string) ($entry['code'] ?? ''));
                $qty = max(0, (int) ($entry['quantity'] ?? 0));
                if ($code === '' || $qty < 1) {
                    continue;
                }
                $def = $this->fetchOrderTypeByCode($pdo, $tenantId, $code);
                if ($def === null || ! (bool) ($def['is_active'] ?? true)) {
                    continue;
                }
                if (! $this->isOrderTypeVisibleInMode($def, $orderMode)) {
                    continue;
                }
                $selfServiceLines[] = [
                    'code' => (string) ($def['code'] ?? $code),
                    'label' => (string) ($def['label'] ?? $code),
                    'service_kind' => (string) ($def['service_kind'] ?? 'full_service'),
                    'quantity' => $qty,
                    'price_per_load' => $effectiveOrderLinePrice($def),
                ];
            }
            if ($selfServiceLines === []) {
                session_flash('errors', ['Tap at least one valid order type for Self Service.']);

                return redirect(route($redirectRoute));
            }
            $orderTypeCodeInput = (string) ($selfServiceLines[0]['code'] ?? '');
        } elseif ($orderMode === 'drop_off') {
            $rawLines = trim((string) $request->input('self_service_lines', ''));
            if ($rawLines !== '') {
                try {
                    $decoded = json_decode($rawLines, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    $decoded = null;
                }
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (! is_array($entry)) {
                            continue;
                        }
                        $code = trim((string) ($entry['code'] ?? ''));
                        $qty = max(0, (int) ($entry['quantity'] ?? 0));
                        if ($code === '' || $qty < 1) {
                            continue;
                        }
                        $def = $this->fetchOrderTypeByCode($pdo, $tenantId, $code);
                        if ($def === null || ! (bool) ($def['is_active'] ?? true)) {
                            continue;
                        }
                        if (! $this->isOrderTypeVisibleInMode($def, $orderMode)) {
                            continue;
                        }
                        $dropOffLines[] = [
                            'code' => (string) ($def['code'] ?? $code),
                            'label' => (string) ($def['label'] ?? $code),
                            'service_kind' => (string) ($def['service_kind'] ?? 'full_service'),
                            'quantity' => $qty,
                            'price_per_load' => $effectiveOrderLinePrice($def),
                            'show_addon_supplies' => (int) ($def['show_addon_supplies'] ?? 1) === 1,
                            'detergent_qty' => $this->resolveOrderTypeSupplyQty($def, 'detergent_qty', (string) ($def['service_kind'] ?? 'full_service'), (string) ($def['supply_block'] ?? 'none')),
                            'fabcon_qty' => $this->resolveOrderTypeSupplyQty($def, 'fabcon_qty', (string) ($def['service_kind'] ?? 'full_service'), (string) ($def['supply_block'] ?? 'none')),
                            'bleach_qty' => $this->resolveOrderTypeSupplyQty($def, 'bleach_qty', (string) ($def['service_kind'] ?? 'full_service'), (string) ($def['supply_block'] ?? 'none')),
                        ];
                    }
                }
            }
        }
        $otDef = null;
        if ($orderMode !== 'add_on_only') {
            $otDef = $this->fetchOrderTypeByCode($pdo, $tenantId, $orderTypeCodeInput);
            if ($otDef === null || ! (bool) ($otDef['is_active'] ?? true)) {
                session_flash('errors', ['Invalid or inactive order type.']);

                return redirect(route($redirectRoute));
            }
            if (! $this->isOrderTypeVisibleInMode($otDef, $orderMode)) {
                session_flash('errors', ['Selected order type is not available for this order mode.']);
                return redirect(route($redirectRoute));
            }
        }
        $serviceKind = (string) ($otDef['service_kind'] ?? ($orderMode === 'add_on_only' ? 'other' : 'full_service'));
        if (! in_array($serviceKind, ['full_service', 'wash_only', 'dry_only', 'rinse_only', 'dry_cleaning', 'fold_only', 'other'], true)) {
            session_flash('errors', ['Order type has an invalid service configuration.']);

            return redirect(route($redirectRoute));
        }
        $supplyBlock = (string) ($otDef['supply_block'] ?? 'none');
        $includedDetergentQty = $this->resolveOrderTypeSupplyQty($otDef, 'detergent_qty', $serviceKind, $supplyBlock);
        $includedFabconQty = $this->resolveOrderTypeSupplyQty($otDef, 'fabcon_qty', $serviceKind, $supplyBlock);
        $includedBleachQty = $this->resolveOrderTypeSupplyQty($otDef, 'bleach_qty', $serviceKind, $supplyBlock);
        $usesCoreSupplies = $includedDetergentQty > 0 || $includedFabconQty > 0 || $includedBleachQty > 0;
        $showAddonSupplies = (bool) ($otDef['show_addon_supplies'] ?? true);
        if ($orderMode === 'drop_off' && $dropOffLines !== []) {
            $includedDetergentQty = 0.0;
            $includedFabconQty = 0.0;
            $includedBleachQty = 0.0;
            $showAddonSupplies = false;
            foreach ($dropOffLines as $line) {
                $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                if ($lineQty < 1) {
                    continue;
                }
                $includedDetergentQty += max(0.0, (float) ($line['detergent_qty'] ?? 0)) * $lineQty;
                $includedFabconQty += max(0.0, (float) ($line['fabcon_qty'] ?? 0)) * $lineQty;
                $includedBleachQty += max(0.0, (float) ($line['bleach_qty'] ?? 0)) * $lineQty;
                if (! empty($line['show_addon_supplies'])) {
                    $showAddonSupplies = true;
                }
            }
            $usesCoreSupplies = $includedDetergentQty > 0 || $includedFabconQty > 0 || $includedBleachQty > 0;
        }
        if ($orderMode === 'self_service') {
            $supplyBlock = 'none';
            $includedDetergentQty = 0.0;
            $includedFabconQty = 0.0;
            $includedBleachQty = 0.0;
            $usesCoreSupplies = false;
            $showAddonSupplies = true;
        } elseif ($orderMode === 'add_on_only') {
            $supplyBlock = 'none';
            $includedDetergentQty = 0.0;
            $includedFabconQty = 0.0;
            $includedBleachQty = 0.0;
            $usesCoreSupplies = false;
            $showAddonSupplies = true;
        }

        $orderTypeCode = $orderMode === 'add_on_only'
            ? 'add_on_only'
            : (string) ($otDef['code'] ?? $orderTypeCodeInput);
        $pricePerLoad = $otDef !== null ? $effectiveOrderLinePrice($otDef) : 0.0;
        $requiredWeight = ! empty($otDef['required_weight']) || $serviceKind === 'dry_cleaning';
        $maxWeightKgPerLoad = max(0.0, (float) ($otDef['max_weight_kg'] ?? 0));
        $excessWeightFeePerKg = max(0.0, (float) ($otDef['excess_weight_fee_per_kg'] ?? 0));
        if ($orderMode === 'self_service' || $orderMode === 'add_on_only') {
            $requiredWeight = false;
            $maxWeightKgPerLoad = 0.0;
            $excessWeightFeePerKg = 0.0;
        }
        $serviceWeight = null;
        if ($requiredWeight && $origin === 'staff_portal') {
            $serviceWeight = round((float) $request->input('service_weight', 0), 3);
            if ($serviceWeight <= 0) {
                session_flash('errors', ['Weight is required for this service.']);

                return redirect(route($redirectRoute));
            }
        }

        $numberOfLoads = max(1, min(100, (int) $request->input('number_of_loads', 1)));
        if ($orderMode === 'self_service') {
            $numberOfLoads = max(1, array_sum(array_map(static fn (array $line): int => (int) ($line['quantity'] ?? 0), $selfServiceLines)));
        } elseif ($orderMode === 'add_on_only') {
            $numberOfLoads = 1;
        } elseif ($dropOffLines !== []) {
            $numberOfLoads = max(1, array_sum(array_map(static fn (array $line): int => (int) ($line['quantity'] ?? 0), $dropOffLines)));
        }
        $dropOffDistinctOrderTypes = 0;
        if ($orderMode === 'drop_off' && $dropOffLines !== []) {
            foreach ($dropOffLines as $line) {
                if (max(0, (int) ($line['quantity'] ?? 0)) > 0) {
                    $dropOffDistinctOrderTypes++;
                }
            }
        }
        if ($orderMode === 'drop_off' && $dropOffDistinctOrderTypes > 1) {
            // Mixed Drop Off order types: actual weight limit does not apply.
            $maxWeightKgPerLoad = 0.0;
            $excessWeightFeePerKg = 0.0;
        }
        $actualWeightKg = round(max(0.0, (float) $request->input('actual_weight_kg', 0)), 3);
        if ($maxWeightKgPerLoad > 0 && ! ($serviceMode === 'free' || $serviceMode === 'reward') && $actualWeightKg <= 0) {
            session_flash('errors', ['Actual weight is required when a maximum weight limit is configured for this service.']);

            return redirect(route($redirectRoute));
        }
        $washQtyInput = $numberOfLoads;
        $dryQtyInput = $numberOfLoads;
        if ($serviceMode === 'reward') {
            $rewardQty = max(1, (int) ($rewardConfig['reward_quantity'] ?? 1));
            $washQtyInput = $rewardQty;
            $dryQtyInput = $rewardQty;
        }
        $washQty = in_array($serviceKind, ['dry_only', 'dry_cleaning', 'fold_only'], true) ? 0 : $washQtyInput;
        $dryQty = in_array($serviceKind, ['wash_only', 'rinse_only', 'dry_cleaning', 'fold_only'], true) ? 0 : $dryQtyInput;
        if ($orderMode === 'self_service') {
            $washQty = 0;
            $dryQty = 0;
            foreach ($selfServiceLines as $line) {
                $lineKind = (string) ($line['service_kind'] ?? 'full_service');
                $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                if ($lineQty < 1) {
                    continue;
                }
                if (! in_array($lineKind, ['dry_only', 'dry_cleaning', 'fold_only'], true)) {
                    $washQty += $lineQty;
                }
                if (! in_array($lineKind, ['wash_only', 'rinse_only', 'dry_cleaning', 'fold_only'], true)) {
                    $dryQty += $lineQty;
                }
            }
            $washQty = max(1, $washQty);
            $dryQty = max(0, $dryQty);
            $actualWeightKg = 0.0;
        } elseif ($dropOffLines !== []) {
            $washQty = 0;
            $dryQty = 0;
            foreach ($dropOffLines as $line) {
                $lineKind = (string) ($line['service_kind'] ?? 'full_service');
                $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                if ($lineQty < 1) {
                    continue;
                }
                if (! in_array($lineKind, ['dry_only', 'dry_cleaning', 'fold_only'], true)) {
                    $washQty += $lineQty;
                }
                if (! in_array($lineKind, ['wash_only', 'rinse_only', 'dry_cleaning', 'fold_only'], true)) {
                    $dryQty += $lineQty;
                }
            }
            $washQty = max(1, $washQty);
            $dryQty = max(0, $dryQty);
        }
        $washerMachineId = 0;
        $dryerMachineId = 0;
        $useMachines = $this->isMachineAssignmentEnabled($pdo, $tenantId);
        $hasReferenceCode = $this->ensureLaundryOrdersReferenceCode($pdo);
        if (! $hasReferenceCode) {
            session_flash('errors', ['Reference number column is missing. Please run/update database migrations, then try again.']);

            return redirect(route($redirectRoute));
        }
        $hasGroupReferenceCode = $this->ensureLaundryOrdersGroupReferenceCode($pdo);
        $referenceCode = $this->resolveUniqueOrderReference($pdo, $tenantId, trim((string) $request->input('reference_code', '')));
        $needsWasher = false;
        $needsDryer = false;

        $inclusionDetergentId = max(0, (int) $request->input('inclusion_detergent_item_id', 0));
        $inclusionFabconId = max(0, (int) $request->input('inclusion_fabcon_item_id', 0));
        $inclusionBleachId = max(0, (int) $request->input('inclusion_bleach_item_id', 0));
        $manualInclusionDetQty = max(0.0, (float) $request->input('inclusion_detergent_qty', 0));
        $manualInclusionFabQty = max(0.0, (float) $request->input('inclusion_fabcon_qty', 0));
        $manualInclusionBleachQty = max(0.0, (float) $request->input('inclusion_bleach_qty', 0));
        $addonDetergentId = max(0, (int) $request->input('addon_detergent_item_id', 0));
        $addonFabconId = max(0, (int) $request->input('addon_fabcon_item_id', 0));
        $addonBleachId = max(0, (int) $request->input('addon_bleach_item_id', 0));
        $addonOtherId = max(0, (int) $request->input('addon_other_item_id', 0));
        $trackGasul = $this->isBranchTrackGasulUsageEnabled($pdo, $tenantId);
        $extraDetergentQty = max(0.0, (float) $request->input('detergent_qty', 0));
        $extraFabconQty = max(0.0, (float) $request->input('fabcon_qty', 0));
        $extraBleachQty = max(0.0, (float) $request->input('bleach_qty', 0));
        $extraOtherQty = max(0.0, (float) $request->input('other_qty', 0));
        $addonOtherLinesRaw = trim((string) $request->input('addon_other_lines', ''));
        $addonOtherSelections = [];
        if ($addonOtherLinesRaw !== '') {
            try {
                $decodedOther = json_decode($addonOtherLinesRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $decodedOther = null;
            }
            if (is_array($decodedOther)) {
                foreach ($decodedOther as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $lineItemId = max(0, (int) ($line['item_id'] ?? 0));
                    $lineQty = max(0.0, (float) ($line['qty'] ?? 0));
                    if ($lineItemId < 1 || $lineQty <= 0) {
                        continue;
                    }
                    $addonOtherSelections[$lineItemId] = ($addonOtherSelections[$lineItemId] ?? 0.0) + $lineQty;
                }
            }
        }
        if ($addonOtherSelections !== []) {
            $firstOtherId = (int) array_key_first($addonOtherSelections);
            $addonOtherId = $firstOtherId;
            $extraOtherQty = (float) array_sum($addonOtherSelections);
        }

        if (! $showAddonSupplies) {
            $extraDetergentQty = 0.0;
            $extraFabconQty = 0.0;
            $extraBleachQty = 0.0;
            $addonDetergentId = 0;
            $addonFabconId = 0;
            $addonBleachId = 0;
            $addonOtherId = 0;
            $extraOtherQty = 0.0;
            $addonOtherSelections = [];
            $trackGasul = false;
        }
        if (! $usesCoreSupplies) {
            $inclusionDetergentId = 0;
            $inclusionFabconId = 0;
            $inclusionBleachId = 0;
        }
        if ($includedDetergentQty <= 0) {
            $inclusionDetergentId = 0;
        }
        if ($includedFabconQty <= 0) {
            $inclusionFabconId = 0;
        }
        if ($includedBleachQty <= 0) {
            $inclusionBleachId = 0;
        }
        if ($includedDetergentQty > 0 && $inclusionDetergentId < 1) {
            session_flash('errors', ['Select detergent for service stock use (included consumption).']);
            return redirect(route($redirectRoute));
        }
        if ($includedFabconQty > 0 && $inclusionFabconId < 1) {
            session_flash('errors', ['Select fabcon for service stock use (included consumption).']);
            return redirect(route($redirectRoute));
        }
        if ($includedBleachQty > 0 && $inclusionBleachId < 1) {
            session_flash('errors', ['Select bleach for service stock use (included consumption).']);
            return redirect(route($redirectRoute));
        }
        if ($orderMode === 'drop_off') {
            if ($inclusionDetergentId > 0 && $manualInclusionDetQty > 0) {
                $includedDetergentQty = $manualInclusionDetQty;
            }
            if ($inclusionFabconId > 0 && $manualInclusionFabQty > 0) {
                $includedFabconQty = $manualInclusionFabQty;
            }
            if ($inclusionBleachId > 0 && $manualInclusionBleachQty > 0) {
                $includedBleachQty = $manualInclusionBleachQty;
            }
        }
        $selectedOrderTypeCodes = [];
        if ($orderMode === 'self_service' && $selfServiceLines !== []) {
            foreach ($selfServiceLines as $line) {
                $lineCode = strtolower(trim((string) ($line['code'] ?? '')));
                $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                if ($lineCode !== '' && $lineQty > 0) {
                    $selectedOrderTypeCodes[] = $lineCode;
                }
            }
        } elseif ($dropOffLines !== []) {
            foreach ($dropOffLines as $line) {
                $lineCode = strtolower(trim((string) ($line['code'] ?? '')));
                $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                if ($lineCode !== '' && $lineQty > 0) {
                    $selectedOrderTypeCodes[] = $lineCode;
                }
            }
        } else {
            $selectedOrderTypeCodes[] = strtolower(trim((string) $orderTypeCode));
        }
        $selectedOrderTypeCodes = array_values(array_unique(array_filter($selectedOrderTypeCodes, static fn (string $code): bool => $code !== '')));
        $requiresGasulTracking = in_array('drop_off', $selectedOrderTypeCodes, true) || in_array('dry_only', $selectedOrderTypeCodes, true);

        $machineType = 'manual';
        $washerIdDb = null;
        $dryerIdDb = null;
        $machineIdLegacy = null;

        $selfServiceFoldQty = max(0, (int) $request->input('fold_service_qty', 0));
        $includeFoldService = $orderMode === 'self_service'
            ? ($selfServiceFoldQty > 0)
            : $request->boolean('include_fold_service');
        if ($serviceKind === 'fold_only') {
            // Fold-only orders always count as folding service for commission routing.
            $includeFoldService = true;
        }
        if ($serviceMode === 'reward' && $rewardConfig !== null) {
            $rewardServiceCode = trim((string) ($rewardConfig['reward_order_type_code'] ?? ''));
            $hasRewardServiceInSelection = false;
            if ($orderMode === 'self_service' && $selfServiceLines !== []) {
                foreach ($selfServiceLines as &$line) {
                    $lineCode = trim((string) ($line['code'] ?? ''));
                    if ($rewardServiceCode !== '' && $lineCode === $rewardServiceCode) {
                        $line['price_per_load'] = 0.0;
                        $hasRewardServiceInSelection = true;
                    }
                }
                unset($line);
            } elseif ($dropOffLines !== []) {
                foreach ($dropOffLines as &$line) {
                    $lineCode = trim((string) ($line['code'] ?? ''));
                    if ($rewardServiceCode !== '' && $lineCode === $rewardServiceCode) {
                        $line['price_per_load'] = 0.0;
                        $hasRewardServiceInSelection = true;
                    }
                }
                unset($line);
            } else {
                if ($rewardServiceCode !== '' && $orderTypeCode === $rewardServiceCode) {
                    $pricePerLoad = 0.0;
                    $hasRewardServiceInSelection = true;
                }
            }
            if (! $hasRewardServiceInSelection) {
                session_flash('errors', ['Reward mode only applies to the configured Reward service. Add the configured service to this order before saving.']);
                return redirect(route($redirectRoute));
            }
        }

        $basePrice = ($requiredWeight && $serviceWeight !== null)
            ? ($pricePerLoad * (float) $serviceWeight)
            : $this->computeBasePriceFromKind($serviceKind, $pricePerLoad, $washQty, $dryQty);
        if ($orderMode === 'self_service') {
            $basePrice = 0.0;
            foreach ($selfServiceLines as $line) {
                $basePrice += max(0, (int) ($line['quantity'] ?? 0)) * max(0.0, (float) ($line['price_per_load'] ?? 0));
            }
        } elseif ($dropOffLines !== []) {
            $basePrice = 0.0;
            foreach ($dropOffLines as $line) {
                $basePrice += max(0, (int) ($line['quantity'] ?? 0)) * max(0.0, (float) ($line['price_per_load'] ?? 0));
            }
        }
        $allowedWeightKg = $maxWeightKgPerLoad > 0 ? ($maxWeightKgPerLoad * $numberOfLoads) : 0.0;
        $excessWeightKg = ($maxWeightKgPerLoad > 0 && $actualWeightKg > $allowedWeightKg)
            ? round($actualWeightKg - $allowedWeightKg, 3)
            : 0.0;
        $excessWeightChargeUnits = $excessWeightKg > 0 ? (float) ceil($excessWeightKg) : 0.0;
        $excessWeightFeeAmount = round($excessWeightChargeUnits * $excessWeightFeePerKg, 4);

        $incDetItem = $includedDetergentQty > 0
            ? $this->getInventoryItemByCategory($pdo, $tenantId, $inclusionDetergentId, 'detergent')
            : null;
        $incFabItem = $includedFabconQty > 0
            ? $this->getInventoryItemByCategory($pdo, $tenantId, $inclusionFabconId, 'fabcon')
            : null;
        $incBleachItem = $includedBleachQty > 0
            ? $this->getInventoryItemByCategory($pdo, $tenantId, $inclusionBleachId, 'bleach')
            : null;
        $incDetQty = ($includedDetergentQty > 0 && $incDetItem !== null) ? $includedDetergentQty : 0.0;
        $incFabQty = ($includedFabconQty > 0 && $incFabItem !== null) ? $includedFabconQty : 0.0;
        $incBleachQty = ($includedBleachQty > 0 && $incBleachItem !== null) ? $includedBleachQty : 0.0;

        $addonDetItem = $this->getInventoryItemByCategory($pdo, $tenantId, $addonDetergentId, 'detergent');
        $addonFabItem = $this->getInventoryItemByCategory($pdo, $tenantId, $addonFabconId, 'fabcon');
        $addonBleachItem = $this->getInventoryItemByCategory($pdo, $tenantId, $addonBleachId, 'bleach');
        $addonOtherItem = $this->getInventoryItemByCategory($pdo, $tenantId, $addonOtherId, 'other');
        $addonOtherItemsById = [];
        foreach ($addonOtherSelections as $otherItemId => $otherQty) {
            if ($otherQty <= 0) {
                continue;
            }
            $item = $this->getInventoryItemByCategory($pdo, $tenantId, (int) $otherItemId, 'other');
            if ($item !== null) {
                $addonOtherItemsById[(int) $otherItemId] = $item;
            }
        }
        if ($orderMode === 'add_on_only'
            && $extraDetergentQty <= 0
            && $extraFabconQty <= 0
            && $extraBleachQty <= 0
            && $extraOtherQty <= 0) {
            session_flash('errors', ['Please select at least one add-on item.']);
            return redirect(route($redirectRoute));
        }

        if ($includedDetergentQty > 0 && $incDetItem === null) {
            session_flash('errors', ['Invalid product selection for detergent (service stock).']);
            return redirect(route($redirectRoute));
        }
        if ($includedFabconQty > 0 && $incFabItem === null) {
            session_flash('errors', ['Invalid product selection for fabcon (service stock).']);
            return redirect(route($redirectRoute));
        }
        if ($includedBleachQty > 0 && $incBleachItem === null) {
            session_flash('errors', ['Invalid product selection for bleach (service stock).']);
            return redirect(route($redirectRoute));
        }

        $addOns = [];
        if ($addonDetItem !== null && $extraDetergentQty > 0) {
            $addOns[] = [$addonDetItem['name'] ?? 'Detergent', $extraDetergentQty, (float) ($addonDetItem['unit_cost'] ?? 10.0)];
        }
        if ($addonFabItem !== null && $extraFabconQty > 0) {
            $addOns[] = [$addonFabItem['name'] ?? 'Fabcon', $extraFabconQty, (float) ($addonFabItem['unit_cost'] ?? 10.0)];
        }
        if ($addonBleachItem !== null && $extraBleachQty > 0) {
            $addOns[] = [$addonBleachItem['name'] ?? 'Bleach', $extraBleachQty, (float) ($addonBleachItem['unit_cost'] ?? 10.0)];
        }
        if ($addonOtherSelections !== []) {
            foreach ($addonOtherSelections as $otherItemId => $otherQty) {
                $item = $addonOtherItemsById[(int) $otherItemId] ?? null;
                if ($item === null || $otherQty <= 0) {
                    continue;
                }
                $addOns[] = [$item['name'] ?? 'Other', $otherQty, (float) ($item['unit_cost'] ?? 10.0)];
            }
        } elseif ($addonOtherItem !== null && $extraOtherQty > 0) {
            $addOns[] = [$addonOtherItem['name'] ?? 'Other', $extraOtherQty, (float) ($addonOtherItem['unit_cost'] ?? 10.0)];
        }
        $addOnTotal = 0.0;
        foreach ($addOns as $entry) {
            $addOnTotal += $entry[1] * $entry[2];
        }
        $isFree = $serviceMode === 'free';
        $isReward = $serviceMode === 'reward';
        if ($isFree) {
            $basePrice = 0.0;
            $addOnTotal = 0.0;
            $excessWeightKg = 0.0;
            $excessWeightFeeAmount = 0.0;
            $addOns = [];
            $extraDetergentQty = 0.0;
            $extraFabconQty = 0.0;
            $extraBleachQty = 0.0;
            $extraOtherQty = 0.0;
        }
        $serviceSubtotal = $basePrice + $excessWeightFeeAmount;
        $foldPricingOrderType = $this->fetchOrderTypeByCode($pdo, $tenantId, 'fold_with_price');
        if (! is_array($foldPricingOrderType)) {
            $foldPricingOrderType = $otDef;
        }
        $foldServiceRate = max(0.0, (float) ($foldPricingOrderType['fold_service_amount'] ?? ($foldPricingOrderType['price_per_load'] ?? 0)));
        $foldQtyForBilling = $orderMode === 'self_service'
            ? $selfServiceFoldQty
            : ($includeFoldService ? max(1, $numberOfLoads) : 0);
        if ($foldQtyForBilling > 0 && $foldServiceRate > 0) {
            $addOns[] = ['Fold Service', (float) $foldQtyForBilling, $foldServiceRate];
            $addOnTotal += ($foldQtyForBilling * $foldServiceRate);
        }
        $totalAmount = $serviceSubtotal + $addOnTotal;
        $discountAmount = 0.0;
        $totalAmountForPayment = $totalAmount;
        $isRewardNoBalanceDue = $isReward && $totalAmountForPayment <= 1e-9;

        $deductionByItemId = [];
        if ($incDetItem !== null && $incDetQty > 0) {
            $deductionByItemId[(int) $incDetItem['id']] = ($deductionByItemId[(int) $incDetItem['id']] ?? 0.0) + $incDetQty;
        }
        if ($incFabItem !== null && $incFabQty > 0) {
            $deductionByItemId[(int) $incFabItem['id']] = ($deductionByItemId[(int) $incFabItem['id']] ?? 0.0) + $incFabQty;
        }
        if ($incBleachItem !== null && $incBleachQty > 0) {
            $deductionByItemId[(int) $incBleachItem['id']] = ($deductionByItemId[(int) $incBleachItem['id']] ?? 0.0) + $incBleachQty;
        }
        if ($addonDetItem !== null && $extraDetergentQty > 0) {
            $deductionByItemId[(int) $addonDetItem['id']] = ($deductionByItemId[(int) $addonDetItem['id']] ?? 0.0) + $extraDetergentQty;
        }
        if ($addonFabItem !== null && $extraFabconQty > 0) {
            $deductionByItemId[(int) $addonFabItem['id']] = ($deductionByItemId[(int) $addonFabItem['id']] ?? 0.0) + $extraFabconQty;
        }
        if ($addonBleachItem !== null && $extraBleachQty > 0) {
            $deductionByItemId[(int) $addonBleachItem['id']] = ($deductionByItemId[(int) $addonBleachItem['id']] ?? 0.0) + $extraBleachQty;
        }
        if ($addonOtherSelections !== []) {
            foreach ($addonOtherSelections as $otherItemId => $otherQty) {
                if ($otherQty <= 0 || ! isset($addonOtherItemsById[(int) $otherItemId])) {
                    continue;
                }
                $deductionByItemId[(int) $otherItemId] = ($deductionByItemId[(int) $otherItemId] ?? 0.0) + $otherQty;
            }
        } elseif ($addonOtherItem !== null && $extraOtherQty > 0) {
            $deductionByItemId[(int) $addonOtherItem['id']] = ($deductionByItemId[(int) $addonOtherItem['id']] ?? 0.0) + $extraOtherQty;
        }

        $gasulId = 0;
        if ($showAddonSupplies) {
            if ($extraDetergentQty > 0 && $addonDetItem === null) {
                session_flash('errors', ['Select a detergent product for the extra quantity.']);

                return redirect(route($redirectRoute));
            }
            if ($extraFabconQty > 0 && $addonFabItem === null) {
                session_flash('errors', ['Select a fabric conditioner for the extra quantity.']);

                return redirect(route($redirectRoute));
            }
            if ($extraBleachQty > 0 && $addonBleachItem === null) {
                session_flash('errors', ['Select a bleach product for the extra quantity.']);

                return redirect(route($redirectRoute));
            }
            if ($addonOtherSelections !== []) {
                foreach ($addonOtherSelections as $otherItemId => $otherQty) {
                    if ($otherQty > 0 && ! isset($addonOtherItemsById[(int) $otherItemId])) {
                        session_flash('errors', ['Select a valid other add-on product for the extra quantity.']);

                        return redirect(route($redirectRoute));
                    }
                }
            } elseif ($extraOtherQty > 0 && $addonOtherItem === null) {
                session_flash('errors', ['Select an other add-on product for the extra quantity.']);

                return redirect(route($redirectRoute));
            }
            if ($trackGasul) {
                $gasulItem = $this->findGasulOtherAddonItem($pdo, $tenantId);
                if (! is_array($gasulItem)) {
                    session_flash('errors', ['Track Gasul is enabled but Gasul item is not configured in Inventory Stocks.']);

                    return redirect(route($redirectRoute));
                }
                $gasulId = (int) ($gasulItem['id'] ?? 0);
                $gasulQty = 0.0;
                if ($addonOtherSelections !== []) {
                    $gasulQty = max(0.0, (float) ($addonOtherSelections[$gasulId] ?? 0));
                } elseif ($addonOtherId === $gasulId) {
                    $gasulQty = $extraOtherQty;
                }
                if ($requiresGasulTracking && ($gasulId < 1 || $gasulQty <= 0)) {
                    session_flash('errors', ['Track Gasul is ON. Please select Gasul in Add-on Other items.']);

                    return redirect(route($redirectRoute));
                }
            }
        }

        $isPaidNowRequest = $paymentTiming === 'pay_now';
        if (! $isPaidNowRequest || $isFree || $isRewardNoBalanceDue) {
            $discountPercentage = 0.0;
        }
        if (! $isFree && ! $isRewardNoBalanceDue && $isPaidNowRequest) {
            $discountAmount = round($totalAmount * ($discountPercentage / 100), 4);
            $totalAmountForPayment = round(max(0.0, $totalAmount - $discountAmount), 4);
        }
        $initialPaymentStatus = 'unpaid';
        $initialPaidAmount = 0.0;
        $initialPaymentMethod = $isFree
            ? 'free'
            : ($isRewardNoBalanceDue
                ? 'reward'
                : ($isPaidNowRequest
                    ? ($requestedPaymentMethod !== '' && $requestedPaymentMethod !== 'pending' ? $requestedPaymentMethod : 'cash')
                    : 'pending'));
        if ($initialPaymentMethod === 'split_payment') {
            if ($splitCashAmount <= 0 && $splitOnlineAmount <= 0) {
                session_flash('errors', ['Enter split payment amounts.']);

                return redirect(route($redirectRoute));
            }
            if ($splitOnlineAmount > 0 && $splitOnlineMethod === '') {
                session_flash('errors', ['Select an online payment method for split payment.']);

                return redirect(route($redirectRoute));
            }
            $splitTotal = round($splitCashAmount + $splitOnlineAmount, 4);
            $hasCashPart = $splitCashAmount > 0.000001;
            if (! $hasCashPart && $splitTotal - $totalAmountForPayment > 0.01) {
                session_flash('errors', ['Split payment cannot exceed the service total unless there is a cash part for change.']);

                return redirect(route($redirectRoute));
            }
        } elseif (! $isFree && ! $isRewardNoBalanceDue && $isPaidNowRequest && $amountTendered <= 0.0) {
            session_flash('errors', ['Enter an amount paid greater than 0.']);

            return redirect(route($redirectRoute));
        } else {
            $splitCashAmount = 0.0;
            $splitOnlineAmount = 0.0;
            $splitOnlineMethod = '';
        }
        $recordedAmountTendered = null;
        $recordedChangeAmount = null;
        if (! $isFree && ! $isRewardNoBalanceDue && $isPaidNowRequest) {
            if ($initialPaymentMethod === 'split_payment') {
                $amountTendered = round($splitCashAmount + $splitOnlineAmount, 4);
            }
            if ($amountTendered <= 0.0) {
                session_flash('errors', ['Enter an amount paid greater than 0.']);

                return redirect(route($redirectRoute));
            }
            $recordedAmountTendered = round(max(0.0, $amountTendered), 4);
            $initialPaidAmount = min($recordedAmountTendered, $totalAmountForPayment);
            $recordedChangeAmount = round(max(0.0, $recordedAmountTendered - $totalAmountForPayment), 4);
            $initialPaymentStatus = ($initialPaidAmount + 0.000001 >= $totalAmountForPayment) ? 'paid' : 'unpaid';
        } elseif ($isFree || $isRewardNoBalanceDue) {
            $initialPaymentStatus = 'paid';
            $initialPaidAmount = $totalAmountForPayment;
        }
        // Initial load status depends on workflow toggle:
        // - Workflow ON  -> regular flow starts at pending
        // - Workflow OFF -> direct payment-state flow (open_ticket/paid)
        // Free/Reward now maps directly to Paid (no Completed stage).
        if ($orderMode === 'add_on_only') {
            $initialLaundryStatus = ($initialPaymentStatus === 'paid') ? 'paid' : 'open_ticket';
        } elseif ($isFree || $isRewardNoBalanceDue) {
            $initialLaundryStatus = 'paid';
        } elseif ($trackLaundryStatus) {
            $initialLaundryStatus = 'pending';
        } else {
            $initialLaundryStatus = ($initialPaymentStatus === 'paid') ? 'paid' : 'open_ticket';
        }

        $savedOrderId = 0;
        $savedReferenceCode = $referenceCode;
        $createdOrderCount = 0;
        $incDetDb = $incDetItem !== null ? (int) $incDetItem['id'] : null;
        $incFabDb = $incFabItem !== null ? (int) $incFabItem['id'] : null;
        $incBleachDb = $incBleachItem !== null ? (int) $incBleachItem['id'] : null;

        $lineSourceForSplit = $orderMode === 'self_service'
            ? $selfServiceLines
            : (($orderMode === 'drop_off' && $dropOffLines !== []) ? $dropOffLines : []);
        if ($lineSourceForSplit === [] && $orderMode === 'drop_off' && $numberOfLoads > 1) {
            $lineSourceForSplit[] = [
                'code' => $orderTypeCode,
                'label' => trim((string) ($otDef['label'] ?? $orderTypeCode)),
                'service_kind' => $serviceKind,
                'quantity' => $numberOfLoads,
                'price_per_load' => $pricePerLoad,
            ];
        }
        $splitEligibleKinds = ['full_service', 'wash_only', 'rinse_only', 'dry_only', 'other'];
        $splitLines = [];
        $splitEligibleLoadCount = 0;
        foreach ($lineSourceForSplit as $line) {
            $lineKind = strtolower(trim((string) ($line['service_kind'] ?? '')));
            $lineQty = max(0, (int) ($line['quantity'] ?? 0));
            if ($lineQty < 1) {
                continue;
            }
            if (! in_array($lineKind, $splitEligibleKinds, true)) {
                continue;
            }
            $splitLines[] = $line;
            $splitEligibleLoadCount += $lineQty;
        }
        if ($orderMode === 'drop_off'
            && $numberOfLoads > 1
            && in_array($serviceKind, $splitEligibleKinds, true)
            && count($lineSourceForSplit) <= 1
            && $splitEligibleLoadCount < $numberOfLoads) {
            $splitLines = [[
                'code' => $orderTypeCode,
                'label' => trim((string) ($otDef['label'] ?? $orderTypeCode)),
                'service_kind' => $serviceKind,
                'quantity' => $numberOfLoads,
                'price_per_load' => $pricePerLoad,
            ]];
            $splitEligibleLoadCount = $numberOfLoads;
        }
        $splitCreationEnabled = $trackMachineMovementEnabled
            && $splitLines !== []
            && $splitEligibleLoadCount > 1;

        if ($splitCreationEnabled) {
            $hasChargedAddOns = false;
            foreach ($addOns as $entry) {
                $entryName = strtolower(trim((string) ($entry[0] ?? '')));
                $entryQty = max(0.0, (float) ($entry[1] ?? 0));
                $entryUnit = max(0.0, (float) ($entry[2] ?? 0));
                if ($entryName === 'fold service' && $entryUnit <= 0.000001) {
                    continue;
                }
                if (($entryQty * $entryUnit) > 0.000001) {
                    $hasChargedAddOns = true;
                    break;
                }
            }
            $splitBlockReasons = [];
            if ($isFree) {
                $splitBlockReasons[] = 'free mode';
            }
            if ($isReward) {
                $splitBlockReasons[] = 'reward mode';
            }
            if ($discountPercentage > 0.000001) {
                $splitBlockReasons[] = 'discount';
            }
            if ($serviceWeight !== null) {
                $splitBlockReasons[] = 'service weight input';
            }
            if ($actualWeightKg > 0.000001) {
                $splitBlockReasons[] = 'actual weight input';
            }
            if ($hasChargedAddOns) {
                $splitBlockReasons[] = 'charged add-ons';
            }
            if ($splitBlockReasons !== []) {
                $reasonText = implode(', ', $splitBlockReasons);
                $splitBlockMessage = 'Multi-load split transaction is blocked by: '.$reasonText.'. Remove these options, then try again.';
                if ($request->wantsJson()) {
                    return json_response([
                        'success' => false,
                        'message' => $splitBlockMessage,
                    ], 422);
                }
                session_flash('errors', [$splitBlockMessage]);
                return redirect(route($redirectRoute));
            }

            $trackKinds = ['full_service', 'wash_only', 'rinse_only', 'dry_only', 'other'];
            $groupReferenceCode = $hasGroupReferenceCode ? $referenceCode : null;
            $pdo->beginTransaction();
            try {
                $orderInsertSql = 'INSERT INTO laundry_orders
                     (tenant_id, created_by_user_id, reference_code, '.($hasGroupReferenceCode ? 'group_reference_code, ' : '').'machine_id, washer_machine_id, dryer_machine_id, customer_id, include_fold_service, inclusion_detergent_item_id, inclusion_fabcon_item_id, inclusion_bleach_item_id, order_type, machine_type, wash_qty, dry_minutes, service_weight, actual_weight_kg, excess_weight_kg, excess_weight_fee_amount, subtotal, add_on_total, total_amount, payment_method, payment_status, split_cash_amount, split_online_amount, split_online_method, is_free, is_reward, reward_config_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, '.($hasGroupReferenceCode ? '?, ' : '').'NULL, NULL, NULL, ?, 0, ?, ?, ?, ?, "manual", ?, ?, NULL, NULL, 0, 0, ?, 0, ?, "pending", "unpaid", 0, 0, NULL, 0, 0, NULL, ?, NOW(), NOW())';
                $orderInsert = $pdo->prepare($orderInsertSql);
                $lineInsert = $pdo->prepare(
                    'INSERT INTO laundry_order_lines (tenant_id, order_id, order_type_code, order_type_label, service_kind, quantity, unit_price, line_total, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );

                $createdRefs = [];
                $receiptRows = [];
                foreach ($splitLines as $line) {
                    $lineKind = (string) ($line['service_kind'] ?? 'full_service');
                    $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                    $lineCode = (string) ($line['code'] ?? $orderTypeCode);
                    $lineLabel = (string) ($line['label'] ?? $lineCode);
                    $linePrice = max(0.0, (float) ($line['price_per_load'] ?? 0));
                    if ($lineQty < 1) {
                        continue;
                    }
                    for ($i = 0; $i < $lineQty; $i++) {
                        $needsTracking = in_array($lineKind, $trackKinds, true);
                        $washLoadQty = in_array($lineKind, ['dry_only', 'dry_cleaning', 'fold_only'], true) ? 0 : 1;
                        $dryLoadQty = in_array($lineKind, ['wash_only', 'rinse_only', 'dry_cleaning', 'fold_only'], true) ? 0 : 1;
                        $initialStatusForLoad = $needsTracking ? 'pending' : 'open_ticket';
                        $orderInsert->execute([
                            $tenantId,
                            $userId > 0 ? $userId : null,
                            $referenceCode,
                            ...($hasGroupReferenceCode ? [$groupReferenceCode] : []),
                            $customerId > 0 ? $customerId : null,
                            $incDetDb,
                            $incFabDb,
                            $incBleachDb,
                            $lineCode,
                            $washLoadQty,
                            $dryLoadQty,
                            $linePrice,
                            $linePrice,
                            $initialStatusForLoad,
                        ]);
                        $newOrderId = (int) $pdo->lastInsertId();
                        if ($newOrderId < 1) {
                            throw new \RuntimeException('Unable to create split transaction.');
                        }
                        $finalRef = $this->resolveUniqueOrderReference($pdo, $tenantId, $this->generateOrderReferenceCandidate($newOrderId));
                        $pdo->prepare(
                            'UPDATE laundry_orders
                             SET reference_code = ?, updated_at = NOW()
                             WHERE tenant_id = ? AND id = ?'
                        )->execute([$finalRef, $tenantId, $newOrderId]);
                        $lineInsert->execute([
                            $tenantId,
                            $newOrderId,
                            $lineCode,
                            $lineLabel,
                            $lineKind,
                            1,
                            $linePrice,
                            $linePrice,
                        ]);
                        $createdOrderCount++;
                        $savedOrderId = $newOrderId;
                        $savedReferenceCode = $finalRef;
                        $createdRefs[] = $finalRef;
                        $receiptRows[] = [
                            'reference_code' => $finalRef,
                            'order_type_label' => $lineLabel,
                            'total_amount' => $linePrice,
                        ];
                    }
                }

                if ($createdOrderCount < 1) {
                    throw new \RuntimeException('No split transactions were created.');
                }
                if ($deductionByItemId !== []) {
                    $this->deductInventoryByItemId($pdo, $tenantId, $deductionByItemId);
                }

                $this->persistBranchBluetoothPrintConfig($pdo, $tenantId, $enableBluetoothPrint);
                $pdo->commit();
                $groupNote = ($hasGroupReferenceCode && $groupReferenceCode !== null)
                    ? (' Group Ref: '.$groupReferenceCode.'.')
                    : '';
                $successMessage = 'Created '.$createdOrderCount.' separate transactions for machine monitoring: '.implode(', ', $createdRefs).'.'.$groupNote;
                if ($request->wantsJson()) {
                    return json_response([
                        'success' => true,
                        'message' => $successMessage,
                        'reference_code' => $savedReferenceCode,
                        'reference_codes' => $createdRefs,
                        'split_receipt_rows' => $receiptRows,
                        'group_reference_code' => $groupReferenceCode,
                        'split_count' => $createdOrderCount,
                        'order_id' => $savedOrderId,
                        'order_type_label' => trim((string) ($otDef['label'] ?? $orderTypeCode)),
                        'service_mode_label' => $isReward ? ($totalAmountForPayment > 1e-9 ? 'Rewards with Payment' : 'Reward') : ($isFree ? 'Free' : 'Regular'),
                        'saved_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                session_flash('success', $successMessage);
                return redirect(route($redirectRoute));
            } catch (\RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($request->wantsJson()) {
                    return json_response(['success' => false, 'message' => $e->getMessage()], 422);
                }
                session_flash('errors', [$e->getMessage()]);
                return redirect(route($redirectRoute));
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($request->wantsJson()) {
                    return json_response(['success' => false, 'message' => $e->getMessage()], 500);
                }
                throw $e;
            }
        }

        $pdo->beginTransaction();
        try {
            $invShort = $this->assertSufficientInventoryForSale($pdo, $tenantId, $deductionByItemId);
            if ($invShort !== null) {
                $pdo->rollBack();
                session_flash('errors', [$invShort]);

                return redirect(route($redirectRoute));
            }

            $pdo->prepare(
                'INSERT INTO laundry_orders
                 (tenant_id, created_by_user_id, reference_code, machine_id, washer_machine_id, dryer_machine_id, customer_id, include_fold_service, inclusion_detergent_item_id, inclusion_fabcon_item_id, inclusion_bleach_item_id, order_type, machine_type, wash_qty, dry_minutes, service_weight, actual_weight_kg, excess_weight_kg, excess_weight_fee_amount, subtotal, add_on_total, total_amount, payment_method, payment_status, split_cash_amount, split_online_amount, split_online_method, is_free, is_reward, reward_config_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([
                $tenantId,
                $userId > 0 ? $userId : null,
                $referenceCode,
                $machineIdLegacy,
                $washerIdDb,
                $dryerIdDb,
                $customerId > 0 ? $customerId : null,
                $includeFoldService ? 1 : 0,
                $incDetDb,
                $incFabDb,
                $incBleachDb,
                $orderTypeCode,
                $machineType,
                $washQty,
                $dryQty,
                $serviceWeight,
                $actualWeightKg > 0 ? $actualWeightKg : null,
                $excessWeightKg,
                $excessWeightFeeAmount,
                $serviceSubtotal,
                $addOnTotal,
                $totalAmountForPayment,
                $initialPaymentMethod,
                $initialPaymentStatus,
                $splitCashAmount,
                $splitOnlineAmount,
                $splitOnlineMethod !== '' ? $splitOnlineMethod : null,
                $isFree ? 1 : 0,
                $isReward ? 1 : 0,
                $isReward && $rewardConfig !== null ? (int) ($rewardConfig['id'] ?? 0) : null,
                $initialLaundryStatus,
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $savedOrderId = $orderId;
            if ($orderId > 0) {
                $setParts = [];
                $setParams = [];
                if ($this->hasLaundryOrdersDiscountPercentage($pdo)) {
                    $setParts[] = 'discount_percentage = ?';
                    $setParams[] = $discountPercentage;
                }
                if ($this->hasLaundryOrdersDiscountAmount($pdo)) {
                    $setParts[] = 'discount_amount = ?';
                    $setParams[] = $discountAmount;
                }
                if ($this->hasLaundryOrdersAmountTendered($pdo)) {
                    $setParts[] = 'amount_tendered = ?';
                    $setParams[] = $recordedAmountTendered;
                }
                if ($this->hasLaundryOrdersChangeAmount($pdo)) {
                    $setParts[] = 'change_amount = ?';
                    $setParams[] = $recordedChangeAmount;
                }
                if ($setParts !== []) {
                    $setParams[] = $tenantId;
                    $setParams[] = $orderId;
                    $pdo->prepare(
                        'UPDATE laundry_orders
                         SET '.implode(', ', $setParts).', updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute($setParams);
                }
            }
            $finalReferenceCode = $this->resolveUniqueOrderReference($pdo, $tenantId, $this->generateOrderReferenceCandidate($orderId));
            if ($finalReferenceCode !== $referenceCode) {
                $pdo->prepare(
                    'UPDATE laundry_orders
                     SET reference_code = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute([$finalReferenceCode, $tenantId, $orderId]);
                $savedReferenceCode = $finalReferenceCode;
            } else {
                $savedReferenceCode = $referenceCode;
            }
            $stAddon = $pdo->prepare(
                'INSERT INTO laundry_order_add_ons (tenant_id, order_id, item_name, quantity, unit_price, total_price)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach (($isFree || $isRewardNoBalanceDue) ? [] : $addOns as $entry) {
                if ($entry[1] <= 0) {
                    continue;
                }
                $stAddon->execute([$tenantId, $orderId, $entry[0], $entry[1], $entry[2], $entry[1] * $entry[2]]);
            }
            if (($orderMode === 'self_service' && $selfServiceLines !== []) || ($orderMode === 'drop_off' && $dropOffLines !== [])) {
                $lineSource = $orderMode === 'self_service' ? $selfServiceLines : $dropOffLines;
                $lineInsert = $pdo->prepare(
                    'INSERT INTO laundry_order_lines (tenant_id, order_id, order_type_code, order_type_label, service_kind, quantity, unit_price, line_total, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                foreach ($lineSource as $line) {
                    $lineQty = max(0, (int) ($line['quantity'] ?? 0));
                    $linePrice = max(0.0, (float) ($line['price_per_load'] ?? 0));
                    if ($lineQty < 1) {
                        continue;
                    }
                    $lineInsert->execute([
                        $tenantId,
                        $orderId,
                        (string) ($line['code'] ?? ''),
                        (string) ($line['label'] ?? ''),
                        (string) ($line['service_kind'] ?? 'full_service'),
                        $lineQty,
                        $linePrice,
                        $lineQty * $linePrice,
                    ]);
                }
            }

            $this->deductInventoryByItemId($pdo, $tenantId, $deductionByItemId);
            $this->recordOrderInventoryMovements($pdo, $tenantId, $orderId, $deductionByItemId, 'deduct', 'Order inventory deduction', $userId > 0 ? $userId : null);
            if ($isReward && $rewardConfig !== null) {
                $this->redeemRewardForOrder($pdo, $tenantId, $customerId, $orderId, $rewardConfig, $userId);
            }
            if (! $isFree && ! $isReward && $initialPaymentStatus === 'paid' && $customerId > 0) {
                $this->applyRewardsCounter($pdo, $tenantId, $customerId, $otDef, $orderId, $userId, [
                    'order_type' => (string) ($otDef['code'] ?? $orderTypeCode),
                    'wash_qty' => $washQty,
                    'dry_minutes' => $dryQty,
                    'dry_qty' => $dryQty,
                ]);
            }
            $this->persistBranchBluetoothPrintConfig($pdo, $tenantId, $enableBluetoothPrint);
            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 422);
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route($redirectRoute));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        $orderTypeLabel = trim((string) ($otDef['label'] ?? ''));
        if ($orderTypeLabel === '') {
            $orderTypeLabel = ucwords(str_replace('_', ' ', $orderTypeCode));
        }
        $serviceModeLabel = $isReward
            ? ($totalAmountForPayment > 1e-9 ? 'Rewards with Payment' : 'Reward')
            : ($isFree ? 'Free' : 'Regular');
        $savedAt = date('Y-m-d H:i:s');
        if ($savedOrderId > 0) {
            try {
                $savedSt = $pdo->prepare('SELECT created_at FROM laundry_orders WHERE tenant_id = ? AND id = ? LIMIT 1');
                $savedSt->execute([$tenantId, $savedOrderId]);
                $savedRow = $savedSt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (is_array($savedRow) && ! empty($savedRow['created_at'])) {
                    $savedAt = (string) $savedRow['created_at'];
                }
            } catch (\Throwable) {
            }
        }
        if (! $trackLaundryStatus) {
            $successMessage = 'Transaction saved. Ref '.$savedReferenceCode.'. Laundry workflow tracking is disabled for this order.';
        } elseif ($initialPaymentStatus === 'paid') {
            $successMessage = 'Transaction saved as paid. Ref '.$savedReferenceCode.'. Continue laundry status flow as needed.';
        } else {
            $successMessage = 'Transaction saved as unpaid. Ref '.$savedReferenceCode.'. Continue laundry status flow as needed.';
        }
        if ($request->wantsJson()) {
            return json_response([
                'success' => true,
                'message' => $successMessage,
                'reference_code' => $savedReferenceCode,
                'order_id' => $savedOrderId,
                'order_type_label' => $orderTypeLabel,
                'service_mode_label' => $serviceModeLabel,
                'saved_at' => $savedAt,
            ]);
        }

        session_flash('success', $successMessage);

        return redirect(route($redirectRoute));
    }

    public function inventoryIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $rows = $pdo->prepare(
            'SELECT *
             FROM laundry_inventory_items
             WHERE tenant_id = ?
             ORDER BY name'
        );
        $rows->execute([$tenantId]);
        $items = $rows->fetchAll(PDO::FETCH_ASSOC);
        return view_page('Inventory Stocks', 'tenant.laundry.inventory', [
            'items' => $items,
            'free_inventory_limit' => null,
        ]);
    }

    public function machinesIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $machinesWasher = $pdo->prepare(
            'SELECT *
             FROM laundry_machines
             WHERE tenant_id = ? AND machine_kind = "washer"
             ORDER BY machine_label ASC, id ASC'
        );
        $machinesWasher->execute([$tenantId]);

        $machinesDryer = $pdo->prepare(
            'SELECT *
             FROM laundry_machines
             WHERE tenant_id = ? AND machine_kind = "dryer"
             ORDER BY machine_label ASC, id ASC'
        );
        $machinesDryer->execute([$tenantId]);

        $washers = $machinesWasher->fetchAll(PDO::FETCH_ASSOC);
        $dryers = $machinesDryer->fetchAll(PDO::FETCH_ASSOC);
        $freeRestricted = Auth::isTenantFreePlanRestricted(Auth::user());
        if ($freeRestricted) {
            $washers = array_slice($washers, 0, self::FREE_LIMIT_WASHERS);
            $dryers = array_slice($dryers, 0, self::FREE_LIMIT_DRYERS);
        }

        return view_page('Machines', 'tenant.laundry.machines', [
            'machines_washer' => $washers,
            'machines_dryer' => $dryers,
            'machine_assignment_enabled' => $this->isMachineAssignmentEnabled($pdo, $tenantId),
            'free_machine_limit_washers' => $freeRestricted ? self::FREE_LIMIT_WASHERS : null,
            'free_machine_limit_dryers' => $freeRestricted ? self::FREE_LIMIT_DRYERS : null,
        ]);
    }

    public function inventoryStore(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $name = trim((string) $request->input('name'));
        $category = trim((string) $request->input('category', 'other'));
        $showItemIn = strtolower(trim((string) $request->input('show_item_in', 'both')));
        $unit = trim((string) $request->input('unit', 'pcs'));
        if (! in_array($showItemIn, ['inclusion', 'addon', 'both'], true)) {
            $showItemIn = 'both';
        }
        $initialQuantity = max(0.0, (float) $request->input('stock_quantity', 0));
        $threshold = max(0.0, (float) $request->input('low_stock_threshold', 0));
        $unitCost = max(0.0, (float) $request->input('unit_cost', 0));
        $allowedCategories = ['detergent', 'fabcon', 'machine_cleaner', 'bleach', 'other'];
        if (! in_array($category, $allowedCategories, true)) {
            $category = 'other';
        }
        if ($name === '') {
            session_flash('errors', ['Item name is required.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }
        $hasImageColumn = $this->ensureLaundryInventoryImagePathColumn($pdo);
        $fileProvided = $this->hasIncomingUpload($request, 'image_file');
        if ($fileProvided && ! $hasImageColumn) {
            session_flash('errors', ['Image column is missing in database (`laundry_inventory_items.image_path`). Run migrations/schema update then try again.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }

        $uploadError = null;
        $imagePath = $this->storeInventoryImageFromUpload($request, $tenantId, $uploadError);
        if ($uploadError !== null) {
            session_flash('errors', [$uploadError]);

            return redirect(route('tenant.laundry-inventory.index'));
        }
        if ($hasImageColumn) {
            $pdo->prepare(
                'INSERT INTO laundry_inventory_items (tenant_id, name, category, show_item_in, unit, low_stock_threshold, stock_quantity, unit_cost, image_path, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE category = VALUES(category), show_item_in = VALUES(show_item_in), low_stock_threshold = VALUES(low_stock_threshold), unit = VALUES(unit), unit_cost = VALUES(unit_cost), image_path = COALESCE(VALUES(image_path), image_path), updated_at = NOW()'
            )->execute([$tenantId, $name, $category, $showItemIn, $unit, $threshold, $initialQuantity, $unitCost, $imagePath !== '' ? $imagePath : null]);
        } else {
            $pdo->prepare(
                'INSERT INTO laundry_inventory_items (tenant_id, name, category, show_item_in, unit, low_stock_threshold, stock_quantity, unit_cost, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE category = VALUES(category), show_item_in = VALUES(show_item_in), low_stock_threshold = VALUES(low_stock_threshold), unit = VALUES(unit), unit_cost = VALUES(unit_cost), updated_at = NOW()'
            )->execute([$tenantId, $name, $category, $showItemIn, $unit, $threshold, $initialQuantity, $unitCost]);
        }

        session_flash('success', 'Inventory item saved.');

        return redirect(route('tenant.laundry-inventory.index'));
    }

    public function inventoryUpdate(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $itemId = max(0, (int) $id);

        $name = trim((string) $request->input('name'));
        $category = trim((string) $request->input('category', 'other'));
        $showItemIn = strtolower(trim((string) $request->input('show_item_in', 'both')));
        $unit = trim((string) $request->input('unit', 'pcs'));
        if (! in_array($showItemIn, ['inclusion', 'addon', 'both'], true)) {
            $showItemIn = 'both';
        }
        $threshold = max(0.0, (float) $request->input('low_stock_threshold', 0));
        $unitCost = max(0.0, (float) $request->input('unit_cost', 0));
        $allowedCategories = ['detergent', 'fabcon', 'machine_cleaner', 'bleach', 'other'];
        if (! in_array($category, $allowedCategories, true)) {
            $category = 'other';
        }
        if ($itemId < 1 || $name === '') {
            session_flash('errors', ['Valid item and item name are required.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }

        $hasImageColumn = $this->ensureLaundryInventoryImagePathColumn($pdo);
        $fileProvided = $this->hasIncomingUpload($request, 'image_file');
        if ($fileProvided && ! $hasImageColumn) {
            session_flash('errors', ['Image column is missing in database (`laundry_inventory_items.image_path`). Run migrations/schema update then try again.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }

        if ($hasImageColumn) {
            $currentSt = $pdo->prepare('SELECT image_path FROM laundry_inventory_items WHERE tenant_id = ? AND id = ? LIMIT 1');
            $currentSt->execute([$tenantId, $itemId]);
            $existing = $currentSt->fetch(PDO::FETCH_ASSOC) ?: [];
            $uploadError = null;
            $imagePath = $this->storeInventoryImageFromUpload($request, $tenantId, $uploadError);
            if ($uploadError !== null) {
                session_flash('errors', [$uploadError]);

                return redirect(route('tenant.laundry-inventory.index'));
            }
            if ($imagePath === '') {
                $imagePath = (string) ($existing['image_path'] ?? '');
            }
            $pdo->prepare(
                'UPDATE laundry_inventory_items
                 SET name = ?, category = ?, show_item_in = ?, unit = ?, low_stock_threshold = ?, unit_cost = ?, image_path = ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([$name, $category, $showItemIn, $unit, $threshold, $unitCost, $imagePath !== '' ? $imagePath : null, $tenantId, $itemId]);
        } else {
            $pdo->prepare(
                'UPDATE laundry_inventory_items
                 SET name = ?, category = ?, show_item_in = ?, unit = ?, low_stock_threshold = ?, unit_cost = ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([$name, $category, $showItemIn, $unit, $threshold, $unitCost, $tenantId, $itemId]);
        }

        session_flash('success', 'Inventory item updated.');

        return redirect(route('tenant.laundry-inventory.index'));
    }

    public function inventoryDestroy(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);
        $itemId = max(0, (int) $id);
        if ($itemId < 1) {
            session_flash('errors', ['Invalid inventory item.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }

        $inUseSt = $pdo->prepare(
            'SELECT COUNT(*) FROM laundry_orders
             WHERE tenant_id = ? AND (
                inclusion_detergent_item_id = ?
                OR inclusion_fabcon_item_id = ?
                OR inclusion_bleach_item_id = ?
             )'
        );
        $inUseSt->execute([$tenantId, $itemId, $itemId, $itemId]);
        $isInUse = (int) $inUseSt->fetchColumn() > 0;
        if ($isInUse) {
            session_flash('errors', ['Cannot delete this inventory item because it is already used in service history.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }
        $itemSt = $pdo->prepare('SELECT id, name, category, is_system_item FROM laundry_inventory_items WHERE tenant_id = ? AND id = ? LIMIT 1');
        $itemSt->execute([$tenantId, $itemId]);
        $item = $itemSt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (! is_array($item)) {
            session_flash('errors', ['Inventory item not found.']);
            return redirect(route('tenant.laundry-inventory.index'));
        }
        if ((int) ($item['is_system_item'] ?? 0) === 1) {
            session_flash('errors', ['This item is required by the system and cannot be deleted.']);
            return redirect(route('tenant.laundry-inventory.index'));
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM laundry_inventory_purchases WHERE tenant_id = ? AND item_id = ?')
                ->execute([$tenantId, $itemId]);
            $pdo->prepare('DELETE FROM laundry_inventory_items WHERE tenant_id = ? AND id = ?')
                ->execute([$tenantId, $itemId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', ['Unable to delete inventory item.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }
        ActivityLogger::log(
            $tenantId,
            $actorId,
            (string) ($actor['role'] ?? 'tenant_admin'),
            'laundry_inventory',
            'destroy_item',
            $request,
            'Deleted inventory item.',
            [
                'item_id' => $itemId,
                'item_name' => (string) ($item['name'] ?? ''),
                'category' => (string) ($item['category'] ?? ''),
            ]
        );

        session_flash('success', 'Inventory item deleted.');

        return redirect(route('tenant.laundry-inventory.index'));
    }

    public function inventoryPurchase(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $itemId = (int) $request->input('item_id');
        $qty = max(0.0, (float) $request->input('quantity', 0));
        $note = trim((string) $request->input('note', ''));
        $stockAction = strtolower(trim((string) $request->input('stock_action', 'add')));
        if (! in_array($stockAction, ['add', 'reduce'], true)) {
            $stockAction = 'add';
        }
        if ($itemId < 1 || $qty <= 0) {
            session_flash('errors', ['Select item and quantity greater than zero.']);

            return redirect(route('tenant.laundry-inventory.index'));
        }

        $pdo->beginTransaction();
        try {
            $itemSt = $pdo->prepare(
                'SELECT unit_cost, stock_quantity
                 FROM laundry_inventory_items
                 WHERE tenant_id = ? AND id = ?
                 LIMIT 1'
            );
            $itemSt->execute([$tenantId, $itemId]);
            $item = $itemSt->fetch(PDO::FETCH_ASSOC);
            if (! is_array($item)) {
                throw new \RuntimeException('Inventory item not found.');
            }
            $unitCost = max(0.0, (float) ($item['unit_cost'] ?? 0));
            $currentStock = max(0.0, (float) ($item['stock_quantity'] ?? 0));
            if ($stockAction === 'reduce' && $currentStock + 1e-9 < $qty) {
                throw new \RuntimeException('Cannot reduce stock beyond remaining quantity.');
            }
            $signedQty = $stockAction === 'reduce' ? -$qty : $qty;
            $pdo->prepare(
                'INSERT INTO laundry_inventory_purchases (tenant_id, item_id, quantity, unit_cost, note, purchased_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([$tenantId, $itemId, $signedQty, $unitCost, $note]);
            $pdo->prepare(
                'UPDATE laundry_inventory_items
                 SET stock_quantity = stock_quantity + ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([$signedQty, $tenantId, $itemId]);
            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.laundry-inventory.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        session_flash('success', $stockAction === 'reduce' ? 'Stock reduced successfully.' : 'Stock added successfully.');

        return redirect(route('tenant.laundry-inventory.index'));
    }

    public function machineStore(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        if ((string) $request->input('update_machine_assignment', '') === '1') {
            $enabled = $request->boolean('machine_assignment_enabled');
            $this->persistMachineAssignmentConfig($pdo, $tenantId, $enabled);
            if ($enabled) {
                // Automatic and manual modes are mutually exclusive.
                $this->persistTrackMachineMovementConfig($pdo, $tenantId, false);
            }
            session_flash('success', 'Machine assignment setting updated.');
            $origin = strtolower(trim((string) $request->input('origin', '')));
            if ($origin === 'sales') {
                return redirect(route('tenant.laundry-sales.index'));
            }
            return redirect(route('tenant.machines.index'));
        }

        $machineLabel = trim((string) $request->input('machine_label'));
        $machineKind = trim((string) $request->input('machine_kind', 'washer'));
        $creditRequired = $request->boolean('credit_required') ? 1 : 0;
        $machineType = $creditRequired === 1 ? 'c5' : 'maytag';
        $creditBalance = $creditRequired === 1 ? max(0.0, (float) $request->input('credit_balance', 0)) : 0.0;
        if (! in_array($machineKind, ['washer', 'dryer'], true)) {
            $machineKind = 'washer';
        }
        if ($machineLabel === '') {
            session_flash('errors', ['Machine label is required.']);

            return redirect(route('tenant.machines.index'));
        }
        if ($this->hasDuplicateMachineLabel($pdo, $tenantId, $machineLabel)) {
            session_flash('errors', ['Machine label already exists. Please use a different label.']);
            return redirect(route('tenant.machines.index'));
        }
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            $limit = $machineKind === 'dryer' ? self::FREE_LIMIT_DRYERS : self::FREE_LIMIT_WASHERS;
            $countSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_machines WHERE tenant_id = ? AND machine_kind = ?');
            $countSt->execute([$tenantId, $machineKind]);
            $count = (int) $countSt->fetchColumn();
            if ($count >= $limit) {
                session_flash('errors', ['Free Mode limit reached: only 1 washer and 1 dryer are allowed.']);
                return redirect(route('tenant.machines.index'));
            }
        }

        $pdo->prepare(
            'INSERT INTO laundry_machines (tenant_id, machine_kind, machine_type, credit_required, credit_balance, machine_label, status, current_order_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, "available", NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE machine_kind = VALUES(machine_kind), machine_type = VALUES(machine_type), credit_required = VALUES(credit_required), credit_balance = VALUES(credit_balance), updated_at = NOW()'
        )->execute([$tenantId, $machineKind, $machineType, $creditRequired, $creditBalance, $machineLabel]);
        $machineId = (int) $pdo->lastInsertId();
        if ($machineId < 1) {
            $stMachine = $pdo->prepare('SELECT id FROM laundry_machines WHERE tenant_id = ? AND LOWER(TRIM(machine_label)) = LOWER(TRIM(?)) LIMIT 1');
            $stMachine->execute([$tenantId, $machineLabel]);
            $machineId = (int) ($stMachine->fetchColumn() ?: 0);
        }
        if ($machineId > 0 && $creditRequired === 1 && $creditBalance > 0) {
            $this->recordMachineCreditMovement(
                $pdo,
                $tenantId,
                $machineId,
                'restock',
                $creditBalance,
                null,
                'Initial machine credit',
                (int) (Auth::user()['id'] ?? 0)
            );
        }

        session_flash('success', 'Machine saved.');

        return redirect(route('tenant.machines.index'));
    }

    public function machineUpdate(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $machineId = max(0, (int) $id);

        $machineLabel = trim((string) $request->input('machine_label'));
        $machineKind = trim((string) $request->input('machine_kind', 'washer'));
        $creditRequired = $request->boolean('credit_required') ? 1 : 0;
        $machineType = $creditRequired === 1 ? 'c5' : 'maytag';
        $creditBalance = $creditRequired === 1 ? max(0.0, (float) $request->input('credit_balance', 0)) : 0.0;
        if (! in_array($machineKind, ['washer', 'dryer'], true)) {
            $machineKind = 'washer';
        }
        if ($machineId < 1 || $machineLabel === '') {
            session_flash('errors', ['Machine label is required.']);

            return redirect(route('tenant.machines.index'));
        }
        $existingSt = $pdo->prepare('SELECT credit_required, credit_balance FROM laundry_machines WHERE tenant_id = ? AND id = ? LIMIT 1');
        $existingSt->execute([$tenantId, $machineId]);
        $existingMachine = $existingSt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($existingMachine)) {
            session_flash('errors', ['Machine not found.']);
            return redirect(route('tenant.machines.index'));
        }
        if ($this->hasDuplicateMachineLabel($pdo, $tenantId, $machineLabel, $machineId)) {
            session_flash('errors', ['Machine label already exists. Please use a different label.']);
            return redirect(route('tenant.machines.index'));
        }
        $pdo->prepare(
            'UPDATE laundry_machines
             SET machine_kind = ?, machine_type = ?, credit_required = ?, credit_balance = ?, machine_label = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        )->execute([$machineKind, $machineType, $creditRequired, $creditBalance, $machineLabel, $tenantId, $machineId]);
        $oldBalance = max(0.0, (float) ($existingMachine['credit_balance'] ?? 0));
        $newBalance = max(0.0, (float) $creditBalance);
        $delta = round($newBalance - $oldBalance, 4);
        if ($delta > 0) {
            $this->recordMachineCreditMovement(
                $pdo,
                $tenantId,
                $machineId,
                'restock',
                $delta,
                null,
                'Manual credit restock',
                (int) (Auth::user()['id'] ?? 0)
            );
        } elseif ($delta < 0) {
            $this->recordMachineCreditMovement(
                $pdo,
                $tenantId,
                $machineId,
                'deduct',
                abs($delta),
                null,
                'Manual credit adjustment',
                (int) (Auth::user()['id'] ?? 0)
            );
        }

        session_flash('success', 'Machine updated.');

        return redirect(route('tenant.machines.index'));
    }

    private function hasDuplicateMachineLabel(PDO $pdo, int $tenantId, string $machineLabel, ?int $excludeId = null): bool
    {
        $normalized = trim($machineLabel);
        if ($normalized === '') {
            return false;
        }
        $sql = 'SELECT 1
                FROM laundry_machines
                WHERE tenant_id = ?
                  AND LOWER(TRIM(machine_label)) = LOWER(TRIM(?))';
        $params = [$tenantId, $normalized];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn() !== false;
    }

    public function machineDestroy(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $machineId = max(0, (int) $id);
        if ($machineId < 1) {
            session_flash('errors', ['Invalid machine selected.']);

            return redirect(route('tenant.machines.index'));
        }

        $st = $pdo->prepare('SELECT status FROM laundry_machines WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $machineId]);
        $machine = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($machine)) {
            session_flash('errors', ['Machine not found.']);

            return redirect(route('tenant.machines.index'));
        }
        if ((string) ($machine['status'] ?? '') === 'running') {
            session_flash('errors', ['Cannot delete a running machine. Complete current transaction first.']);

            return redirect(route('tenant.machines.index'));
        }

        $pdo->prepare('DELETE FROM laundry_machines WHERE tenant_id = ? AND id = ?')
            ->execute([$tenantId, $machineId]);
        session_flash('success', 'Machine deleted.');

        return redirect(route('tenant.machines.index'));
    }

    public function orderTypePricingIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $st = $pdo->prepare(
            'SELECT * FROM laundry_order_types WHERE tenant_id = ? ORDER BY id ASC'
        );
        $st->execute([$tenantId]);
        $orderTypes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $orderTypeTransactionCounts = [];
        try {
            $cntSt = $pdo->prepare(
                'SELECT order_type AS code, COUNT(*) AS cnt
                 FROM laundry_orders
                 WHERE tenant_id = ?
                 GROUP BY order_type'
            );
            $cntSt->execute([$tenantId]);
            foreach ($cntSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $c = (string) ($row['code'] ?? '');
                if ($c !== '') {
                    $orderTypeTransactionCounts[$c] = (int) ($row['cnt'] ?? 0);
                }
            }
        } catch (\Throwable) {
            $orderTypeTransactionCounts = [];
        }

        return view_page('Order Pricing', 'tenant.laundry.order-type-pricing', [
            'order_types' => $orderTypes,
            'order_type_transaction_counts' => $orderTypeTransactionCounts,
            'reward_system_active' => $this->rewardProgramIsActive($pdo, $tenantId),
            'core_order_type_codes' => LaundrySchema::CORE_ORDER_TYPE_CODES,
        ]);
    }

    public function orderTypeCreate(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $label = trim((string) $request->input('label'));
        $serviceKind = (string) $request->input('service_kind', 'full_service');
        if (! in_array($serviceKind, ['full_service', 'wash_only', 'dry_only', 'rinse_only', 'dry_cleaning', 'fold_only', 'other'], true)) {
            $serviceKind = 'full_service';
        }
        $supplyBlock = (string) $request->input('supply_block', $this->defaultSupplyBlockForServiceKind($serviceKind));
        if (! in_array($supplyBlock, ['none', 'full_service', 'full_service_2x', 'wash_supplies', 'rinse_supplies'], true)) {
            $supplyBlock = $this->defaultSupplyBlockForServiceKind($serviceKind);
        }
        $showAddon = $request->has('show_addon_supplies')
            ? $request->boolean('show_addon_supplies')
            : $this->defaultShowAddonSuppliesForServiceKind($serviceKind);
        $showInOrderMode = $this->normalizeOrderTypeShowMode((string) $request->input('show_in_order_mode', 'both'));
        $requiredWeight = $request->boolean('required_weight');
        $detergentQty = max(0.0, round((float) $request->input('detergent_qty', 0), 3));
        $fabconQty = max(0.0, round((float) $request->input('fabcon_qty', 0), 3));
        $bleachQty = max(0.0, round((float) $request->input('bleach_qty', 0), 3));
        $price = max(0.0, (float) $request->input('price_per_load', 0));
        $foldServiceAmount = max(0.0, (float) $request->input('fold_service_amount', 0));
        $foldStaffShareAmount = max(0.0, (float) $request->input('fold_staff_share_amount', 0));
        $foldCommissionTarget = strtolower(trim((string) $request->input('fold_commission_target', 'branch')));
        if (! in_array($foldCommissionTarget, ['branch', 'staff'], true)) {
            $foldCommissionTarget = 'branch';
        }
        if ($serviceKind !== 'fold_only') {
            $foldServiceAmount = 0.0;
            $foldStaffShareAmount = 0.0;
            $foldCommissionTarget = 'branch';
        }
        $maxWeightKg = max(0.0, round((float) $request->input('max_weight_kg', 0), 3));
        $excessWeightFeePerKg = max(0.0, round((float) $request->input('excess_weight_fee_per_kg', 0), 4));
        if ($maxWeightKg <= 0) {
            $excessWeightFeePerKg = 0.0;
        }
        if ($serviceKind === 'dry_cleaning') {
            $supplyBlock = 'none';
            $requiredWeight = true;
            $detergentQty = 0.0;
            $fabconQty = 0.0;
            $bleachQty = 0.0;
            $showAddon = false;
        } elseif ($serviceKind === 'fold_only') {
            $supplyBlock = 'none';
            $requiredWeight = false;
            $detergentQty = 0.0;
            $fabconQty = 0.0;
            $bleachQty = 0.0;
            $showAddon = false;
            $maxWeightKg = 0.0;
            $excessWeightFeePerKg = 0.0;
        }

        if ($label === '') {
            session_flash('errors', ['Enter a display name for the order type.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }

        $code = $serviceKind === 'dry_cleaning'
            ? 'dry_cleaning'
            : $this->generateUniqueOrderTypeCode($pdo, $tenantId, $label);
        if ($serviceKind === 'dry_cleaning' && $this->orderTypeCodeExists($pdo, $tenantId, $code)) {
            session_flash('errors', ['A "Dry Cleaning" service already exists. Edit it instead of adding another one.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }
        $includeInRewards = $this->resolveIncludeInRewardsForSave($pdo, $tenantId, $serviceKind, $request, false);
        $hasFoldAmountColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_service_amount');
        $hasFoldTargetColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_commission_target');
        $hasFoldStaffShareColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_staff_share_amount');
        $hasShowInModeColumn = $this->hasColumn($pdo, 'laundry_order_types', 'show_in_order_mode');
        $hasDetergentQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'detergent_qty');
        $hasFabconQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fabcon_qty');
        $hasBleachQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'bleach_qty');
        if ($hasFoldAmountColumn && $hasFoldTargetColumn && $hasDetergentQtyColumn && $hasFabconQtyColumn && $hasBleachQtyColumn) {
            if ($hasFoldStaffShareColumn) {
                $pdo->prepare(
                    $hasShowInModeColumn
                        ? 'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, show_in_order_mode, supply_block, show_addon_supplies, required_weight, detergent_qty, fabcon_qty, bleach_qty, fold_service_amount, fold_commission_target, fold_staff_share_amount, price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, is_active, include_in_rewards, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
                        : 'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, supply_block, show_addon_supplies, required_weight, detergent_qty, fabcon_qty, bleach_qty, fold_service_amount, fold_commission_target, fold_staff_share_amount, price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, is_active, include_in_rewards, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
                )->execute(
                    $hasShowInModeColumn
                        ? [$tenantId, $code, $label, $serviceKind, $showInOrderMode, $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $foldStaffShareAmount, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $includeInRewards]
                        : [$tenantId, $code, $label, $serviceKind, $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $foldStaffShareAmount, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $includeInRewards]
                );
            } else {
                $pdo->prepare(
                    $hasShowInModeColumn
                        ? 'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, show_in_order_mode, supply_block, show_addon_supplies, required_weight, detergent_qty, fabcon_qty, bleach_qty, fold_service_amount, fold_commission_target, price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, is_active, include_in_rewards, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
                        : 'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, supply_block, show_addon_supplies, required_weight, detergent_qty, fabcon_qty, bleach_qty, fold_service_amount, fold_commission_target, price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, is_active, include_in_rewards, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
                )->execute(
                    $hasShowInModeColumn
                        ? [$tenantId, $code, $label, $serviceKind, $showInOrderMode, $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $includeInRewards]
                        : [$tenantId, $code, $label, $serviceKind, $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $includeInRewards]
                );
            }
        } else {
            $pdo->prepare(
                $hasShowInModeColumn
                    ? 'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, show_in_order_mode, supply_block, show_addon_supplies, required_weight, price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, is_active, include_in_rewards, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
                    : 'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, supply_block, show_addon_supplies, required_weight, price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, is_active, include_in_rewards, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
            )->execute(
                $hasShowInModeColumn
                    ? [$tenantId, $code, $label, $serviceKind, $showInOrderMode, $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $includeInRewards]
                    : [$tenantId, $code, $label, $serviceKind, $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $includeInRewards]
            );
        }

        session_flash('success', 'Order type added.');

        return redirect(route('tenant.laundry-order-pricing.index'));
    }

    public function orderTypeUpdate(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $rowId = max(0, (int) $id);

        $st = $pdo->prepare('SELECT id, code FROM laundry_order_types WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $rowId]);
        $existingRow = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($existingRow)) {
            session_flash('errors', ['Order type not found.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }
        $existingCode = strtolower(trim((string) ($existingRow['code'] ?? '')));

        $label = trim((string) $request->input('label'));
        $serviceKind = (string) $request->input('service_kind', 'full_service');
        if (! in_array($serviceKind, ['full_service', 'wash_only', 'dry_only', 'rinse_only', 'dry_cleaning', 'fold_only', 'other'], true)) {
            $serviceKind = 'full_service';
        }
        $supplyBlock = (string) $request->input('supply_block', $this->defaultSupplyBlockForServiceKind($serviceKind));
        if (! in_array($supplyBlock, ['none', 'full_service', 'full_service_2x', 'wash_supplies', 'rinse_supplies'], true)) {
            $supplyBlock = $this->defaultSupplyBlockForServiceKind($serviceKind);
        }
        $showAddon = $request->boolean('show_addon_supplies');
        $showInOrderMode = $this->normalizeOrderTypeShowMode((string) $request->input('show_in_order_mode', 'both'));
        $requiredWeight = $request->boolean('required_weight');
        $detergentQty = max(0.0, round((float) $request->input('detergent_qty', 0), 3));
        $fabconQty = max(0.0, round((float) $request->input('fabcon_qty', 0), 3));
        $bleachQty = max(0.0, round((float) $request->input('bleach_qty', 0), 3));
        $price = max(0.0, (float) $request->input('price_per_load', 0));
        $foldServiceAmount = max(0.0, (float) $request->input('fold_service_amount', 0));
        $foldStaffShareAmount = max(0.0, (float) $request->input('fold_staff_share_amount', 0));
        $foldCommissionTarget = strtolower(trim((string) $request->input('fold_commission_target', 'branch')));
        if (! in_array($foldCommissionTarget, ['branch', 'staff'], true)) {
            $foldCommissionTarget = 'branch';
        }
        if ($existingCode === 'free_fold') {
            $price = 0.0;
            $foldServiceAmount = 0.0;
            $foldStaffShareAmount = 0.0;
            $foldCommissionTarget = 'branch';
        }
        if ($serviceKind !== 'fold_only') {
            $foldServiceAmount = 0.0;
            $foldStaffShareAmount = 0.0;
            $foldCommissionTarget = 'branch';
        }
        $maxWeightKg = max(0.0, round((float) $request->input('max_weight_kg', 0), 3));
        $excessWeightFeePerKg = max(0.0, round((float) $request->input('excess_weight_fee_per_kg', 0), 4));
        if ($maxWeightKg <= 0) {
            $excessWeightFeePerKg = 0.0;
        }
        $isActive = $request->boolean('is_active');
        if ($serviceKind === 'dry_cleaning') {
            $supplyBlock = 'none';
            $requiredWeight = true;
            $detergentQty = 0.0;
            $fabconQty = 0.0;
            $bleachQty = 0.0;
            $showAddon = false;
        } elseif ($serviceKind === 'fold_only') {
            $supplyBlock = 'none';
            $requiredWeight = false;
            $detergentQty = 0.0;
            $fabconQty = 0.0;
            $bleachQty = 0.0;
            $showAddon = false;
            $maxWeightKg = 0.0;
            $excessWeightFeePerKg = 0.0;
        }

        if ($label === '') {
            session_flash('errors', ['Enter a display name for the order type.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }

        $includeInRewards = $this->resolveIncludeInRewardsForSave($pdo, $tenantId, $serviceKind, $request, true);
        $hasFoldAmountColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_service_amount');
        $hasFoldTargetColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_commission_target');
        $hasFoldStaffShareColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_staff_share_amount');
        $hasShowInModeColumn = $this->hasColumn($pdo, 'laundry_order_types', 'show_in_order_mode');
        $hasDetergentQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'detergent_qty');
        $hasFabconQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fabcon_qty');
        $hasBleachQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'bleach_qty');
        if ($includeInRewards !== null) {
            if ($hasFoldAmountColumn && $hasFoldTargetColumn && $hasDetergentQtyColumn && $hasFabconQtyColumn && $hasBleachQtyColumn) {
                if ($hasFoldStaffShareColumn) {
                    $pdo->prepare(
                        'UPDATE laundry_order_types SET label = ?, service_kind = ?, '.($hasShowInModeColumn ? 'show_in_order_mode = ?, ' : '').'supply_block = ?, show_addon_supplies = ?, required_weight = ?, detergent_qty = ?, fabcon_qty = ?, bleach_qty = ?, fold_service_amount = ?, fold_commission_target = ?, fold_staff_share_amount = ?, price_per_load = ?, max_weight_kg = ?, excess_weight_fee_per_kg = ?, sort_order = ?, is_active = ?, include_in_rewards = ?, updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute(array_values(array_filter([
                        $label, $serviceKind,
                        $hasShowInModeColumn ? $showInOrderMode : null,
                        $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $foldStaffShareAmount, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $isActive ? 1 : 0, $includeInRewards, $tenantId, $rowId
                    ], static fn ($v) => $v !== null)));
                } else {
                    $pdo->prepare(
                        'UPDATE laundry_order_types SET label = ?, service_kind = ?, '.($hasShowInModeColumn ? 'show_in_order_mode = ?, ' : '').'supply_block = ?, show_addon_supplies = ?, required_weight = ?, detergent_qty = ?, fabcon_qty = ?, bleach_qty = ?, fold_service_amount = ?, fold_commission_target = ?, price_per_load = ?, max_weight_kg = ?, excess_weight_fee_per_kg = ?, sort_order = ?, is_active = ?, include_in_rewards = ?, updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute(array_values(array_filter([
                        $label, $serviceKind,
                        $hasShowInModeColumn ? $showInOrderMode : null,
                        $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $isActive ? 1 : 0, $includeInRewards, $tenantId, $rowId
                    ], static fn ($v) => $v !== null)));
                }
            } else {
                $pdo->prepare(
                    'UPDATE laundry_order_types SET label = ?, service_kind = ?, '.($hasShowInModeColumn ? 'show_in_order_mode = ?, ' : '').'supply_block = ?, show_addon_supplies = ?, required_weight = ?, price_per_load = ?, max_weight_kg = ?, excess_weight_fee_per_kg = ?, sort_order = ?, is_active = ?, include_in_rewards = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute(array_values(array_filter([
                    $label, $serviceKind,
                    $hasShowInModeColumn ? $showInOrderMode : null,
                    $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $isActive ? 1 : 0, $includeInRewards, $tenantId, $rowId
                ], static fn ($v) => $v !== null)));
            }
        } else {
            if ($hasFoldAmountColumn && $hasFoldTargetColumn && $hasDetergentQtyColumn && $hasFabconQtyColumn && $hasBleachQtyColumn) {
                if ($hasFoldStaffShareColumn) {
                    $pdo->prepare(
                        'UPDATE laundry_order_types SET label = ?, service_kind = ?, '.($hasShowInModeColumn ? 'show_in_order_mode = ?, ' : '').'supply_block = ?, show_addon_supplies = ?, required_weight = ?, detergent_qty = ?, fabcon_qty = ?, bleach_qty = ?, fold_service_amount = ?, fold_commission_target = ?, fold_staff_share_amount = ?, price_per_load = ?, max_weight_kg = ?, excess_weight_fee_per_kg = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute(array_values(array_filter([
                        $label, $serviceKind,
                        $hasShowInModeColumn ? $showInOrderMode : null,
                        $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $foldStaffShareAmount, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $isActive ? 1 : 0, $tenantId, $rowId
                    ], static fn ($v) => $v !== null)));
                } else {
                    $pdo->prepare(
                        'UPDATE laundry_order_types SET label = ?, service_kind = ?, '.($hasShowInModeColumn ? 'show_in_order_mode = ?, ' : '').'supply_block = ?, show_addon_supplies = ?, required_weight = ?, detergent_qty = ?, fabcon_qty = ?, bleach_qty = ?, fold_service_amount = ?, fold_commission_target = ?, price_per_load = ?, max_weight_kg = ?, excess_weight_fee_per_kg = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute(array_values(array_filter([
                        $label, $serviceKind,
                        $hasShowInModeColumn ? $showInOrderMode : null,
                        $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $detergentQty, $fabconQty, $bleachQty, $foldServiceAmount, $foldCommissionTarget, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $isActive ? 1 : 0, $tenantId, $rowId
                    ], static fn ($v) => $v !== null)));
                }
            } else {
                $pdo->prepare(
                    'UPDATE laundry_order_types SET label = ?, service_kind = ?, '.($hasShowInModeColumn ? 'show_in_order_mode = ?, ' : '').'supply_block = ?, show_addon_supplies = ?, required_weight = ?, price_per_load = ?, max_weight_kg = ?, excess_weight_fee_per_kg = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute(array_values(array_filter([
                    $label, $serviceKind,
                    $hasShowInModeColumn ? $showInOrderMode : null,
                    $supplyBlock, $showAddon ? 1 : 0, $requiredWeight ? 1 : 0, $price, $maxWeightKg, $excessWeightFeePerKg, 0, $isActive ? 1 : 0, $tenantId, $rowId
                ], static fn ($v) => $v !== null)));
            }
        }

        session_flash('success', 'Order type updated.');

        return redirect(route('tenant.laundry-order-pricing.index'));
    }

    public function orderTypeDestroy(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);
        $rowId = max(0, (int) $id);

        $st = $pdo->prepare('SELECT id, code FROM laundry_order_types WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $rowId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            session_flash('errors', ['Order type not found.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }
        $code = (string) ($row['code'] ?? '');

        if (in_array($code, LaundrySchema::CORE_ORDER_TYPE_CODES, true)) {
            session_flash('errors', ['This order type is included with every shop and cannot be deleted. Optional types you add (for example Dry Cleaning or Other) can still be removed.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }

        $totalOrdersSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_orders WHERE tenant_id = ? AND order_type = ?');
        $totalOrdersSt->execute([$tenantId, $code]);
        $totalOrdersForType = (int) $totalOrdersSt->fetchColumn();
        $purgeConfirmed = $request->boolean('confirm_purge_orders');

        if ($totalOrdersForType > 0 && ! $purgeConfirmed) {
            session_flash('errors', ['This order type still has saved transactions. Open this page with JavaScript enabled, use Delete again, and choose Yes to remove those transactions and the order type.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }

        $totalSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_order_types WHERE tenant_id = ?');
        $totalSt->execute([$tenantId]);
        if ((int) $totalSt->fetchColumn() <= 1) {
            session_flash('errors', ['You must keep at least one order type.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }

        $removedOrders = 0;
        $pdo->beginTransaction();
        try {
            if ($totalOrdersForType > 0) {
                $this->purgeLaundryOrdersByOrderTypeCode($pdo, $tenantId, $code);
            }
            $leftSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_orders WHERE tenant_id = ? AND order_type = ?');
            $leftSt->execute([$tenantId, $code]);
            if ((int) $leftSt->fetchColumn() > 0) {
                throw new \RuntimeException('Linked transactions could not be fully removed.');
            }
            $removedOrders = $totalOrdersForType;
            $pdo->prepare('DELETE FROM laundry_order_types WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $rowId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', ['Could not delete this order type. It may still be referenced elsewhere.']);

            return redirect(route('tenant.laundry-order-pricing.index'));
        }

        if ($removedOrders > 0) {
            session_flash('success', sprintf('Order type removed. %d related transaction(s) were permanently deleted.', $removedOrders));
        } else {
            session_flash('success', 'Order type removed.');
        }
        ActivityLogger::log(
            $tenantId,
            $actorId,
            (string) ($actor['role'] ?? 'tenant_admin'),
            'laundry_order_types',
            'destroy',
            $request,
            'Deleted order type.',
            ['order_type_id' => $rowId, 'order_type_code' => $code, 'purged_orders' => $removedOrders]
        );

        return redirect(route('tenant.laundry-order-pricing.index'));
    }

    /**
     * Deletes all laundry orders (any status) for the given type code, plus dependent rows that are not ON DELETE CASCADE.
     */
    private function purgeLaundryOrdersByOrderTypeCode(PDO $pdo, int $tenantId, string $orderTypeCode): void
    {
        if ($tenantId < 1 || $orderTypeCode === '') {
            return;
        }
        try {
            $pdo->prepare(
                'DELETE re FROM laundry_reward_events re
                 INNER JOIN laundry_orders o ON o.id = re.order_id AND o.tenant_id = re.tenant_id
                 WHERE o.tenant_id = ? AND o.order_type = ?'
            )->execute([$tenantId, $orderTypeCode]);
        } catch (\Throwable) {
        }
        try {
            $pdo->prepare(
                'DELETE rr FROM laundry_reward_redemptions rr
                 INNER JOIN laundry_orders o ON o.id = rr.order_id AND o.tenant_id = rr.tenant_id
                 WHERE o.tenant_id = ? AND o.order_type = ?'
            )->execute([$tenantId, $orderTypeCode]);
        } catch (\Throwable) {
        }
        try {
            $pdo->prepare(
                'UPDATE laundry_machines m
                 INNER JOIN laundry_orders o ON o.id = m.current_order_id AND o.tenant_id = m.tenant_id
                 SET m.current_order_id = NULL
                 WHERE m.tenant_id = ? AND o.order_type = ?'
            )->execute([$tenantId, $orderTypeCode]);
        } catch (\Throwable) {
        }
        $pdo->prepare('DELETE FROM laundry_orders WHERE tenant_id = ? AND order_type = ?')->execute([$tenantId, $orderTypeCode]);
    }

    public function advanceTransactionStatus(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $trackingEnabled = $this->isLaundryStatusTrackingEnabled($pdo, $tenantId);
        $toStatus = strtolower(trim((string) $request->input('to_status', '')));
        if (! in_array($toStatus, ['washing_drying', 'open_ticket', 'pending'], true)) {
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => 'Invalid next status.'], 422);
            }
            session_flash('errors', ['Invalid next status.']);

            return redirect(route('tenant.laundry-sales.index'));
        }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT id, machine_id, washer_machine_id, dryer_machine_id, machine_type, order_type, wash_qty, status, payment_method, payment_status, is_free, is_reward, track_machine_stage, drying_end_at
                 FROM laundry_orders
                 WHERE tenant_id = ? AND id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $st->execute([$tenantId, $id]);
            $order = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if (! $order) {
                throw new \RuntimeException('Transaction not found.');
            }
            if ((int) ($order['is_void'] ?? 0) === 1 || (string) ($order['status'] ?? '') === 'void') {
                throw new \RuntimeException('VOID transactions are read-only.');
            }
            $current = (string) ($order['status'] ?? '');
            if (! $trackingEnabled) {
                throw new \RuntimeException('Status transitions are disabled when workflow tracking is off.');
            }
            $canTransition = false;
            if ($current === 'pending' && $toStatus === 'washing_drying') {
                $canTransition = true;
            } elseif (in_array($current, ['washing_drying', 'running'], true) && $toStatus === 'open_ticket') {
                $canTransition = true;
            } elseif (in_array($current, ['washing_drying', 'running'], true) && $toStatus === 'pending') {
                $canTransition = true;
            }
            if (! $canTransition) {
                throw new \RuntimeException('Invalid status sequence. Move forward only.');
            }

            if ($toStatus === 'open_ticket'
                && $trackingEnabled
                && $this->isTrackMachineMovementEnabled($pdo, $tenantId)
                && in_array($current, ['washing_drying', 'running'], true)) {
                $dryingEndAtRaw = trim((string) ($order['drying_end_at'] ?? ''));
                if ($dryingEndAtRaw === '') {
                    throw new \RuntimeException('Cannot move to Unpaid/Paid yet. Drying end time is not set.');
                }
            }

            if ($toStatus === 'pending') {
                if ((string) ($order['payment_status'] ?? '') === 'paid') {
                    throw new \RuntimeException('Cannot move back to Pending after payment is marked paid.');
                }
                $this->restoreMachineCreditsForOrder($pdo, $tenantId, $id);
                $pdo->prepare(
                    'UPDATE laundry_machines
                     SET status = "available", current_order_id = NULL, updated_at = NOW()
                     WHERE tenant_id = ? AND current_order_id = ?'
                )->execute([$tenantId, $id]);
                $pdo->prepare(
                    'UPDATE laundry_orders
                     SET status = "pending",
                         payment_method = "pending",
                         payment_status = "unpaid",
                         machine_id = NULL,
                         washer_machine_id = NULL,
                         dryer_machine_id = NULL,
                         machine_type = "manual",
                         track_machine_stage = NULL,
                         wash_rinse_minutes = NULL,
                         wash_rinse_machine_id = NULL,
                         wash_rinse_started_at = NULL,
                         wash_rinse_end_at = NULL,
                         drying_minutes = NULL,
                         drying_machine_id = NULL,
                         drying_started_at = NULL,
                         drying_end_at = NULL,
                         movement_completed_at = NULL,
                         movement_last_error = NULL,
                         updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute([$tenantId, $id]);
                $pdo->commit();
                if ($request->wantsJson()) {
                    return json_response(['success' => true, 'message' => 'Moved back to Pending. Machine credit restored.']);
                }
                session_flash('success', 'Moved back to Pending. Machine credit restored.');
                return redirect(route('tenant.laundry-sales.index'));
            }

            if ($toStatus === 'washing_drying') {
                $trackMachineMovementEnabled = $this->isTrackMachineMovementEnabled($pdo, $tenantId);
                $defaultDryingMinutes = $this->getBranchDefaultDryingMinutes($pdo, $tenantId);
                $machineAssignmentEnabled = $this->isMachineAssignmentEnabled($pdo, $tenantId);
                $orderType = $this->fetchOrderTypeByCode($pdo, $tenantId, (string) ($order['order_type'] ?? ''));
                $serviceKind = (string) ($orderType['service_kind'] ?? 'full_service');
                if (! in_array($serviceKind, ['full_service', 'wash_only', 'dry_only', 'rinse_only', 'dry_cleaning', 'fold_only', 'other'], true)) {
                    $serviceKind = 'full_service';
                }
                if ($trackMachineMovementEnabled) {
                    $washRinseMinutes = max(0, (int) $request->input('wash_rinse_minutes', 0));
                    if (in_array($serviceKind, ['full_service', 'wash_only', 'rinse_only'], true)) {
                        if ($washRinseMinutes < 1) {
                            throw new \RuntimeException('Enter wash/rinse minutes greater than 0.');
                        }
                        $washer = $this->fetchFirstAvailableMachineByKind($pdo, $tenantId, 'washer');
                        if (! is_array($washer) || (int) ($washer['id'] ?? 0) < 1) {
                            throw new \RuntimeException('No available washing machine.');
                        }
                        $markWasherRunning = $pdo->prepare(
                            'UPDATE laundry_machines
                             SET status = "running", current_order_id = ?, updated_at = NOW()
                             WHERE tenant_id = ? AND id = ? AND status = "available"'
                        );
                        $markWasherRunning->execute([$id, $tenantId, (int) $washer['id']]);
                        if ($markWasherRunning->rowCount() < 1) {
                            throw new \RuntimeException('No available washing machine.');
                        }
                        $usageQty = max(1, (int) ($order['wash_qty'] ?? 1));
                        $this->deductMachineCredits($pdo, $tenantId, [$washer], $usageQty, $id);
                        $this->applyLoadCardUsage(
                            $pdo,
                            $tenantId,
                            (string) ($washer['machine_type'] ?? ''),
                            $serviceKind,
                            $usageQty
                        );
                        $pdo->prepare(
                            'UPDATE laundry_orders
                             SET status = "washing_drying",
                                 track_machine_stage = "washing_rinsing",
                                 wash_rinse_minutes = ?,
                                 wash_rinse_machine_id = ?,
                                 wash_rinse_started_at = NOW(),
                                 wash_rinse_end_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                                 drying_minutes = ?,
                                 drying_machine_id = NULL,
                                 drying_started_at = NULL,
                                 drying_end_at = NULL,
                                 movement_completed_at = NULL,
                                 movement_last_error = NULL,
                                 machine_id = ?,
                                 washer_machine_id = ?,
                                 dryer_machine_id = NULL,
                                 machine_type = "washer",
                                 updated_at = NOW()
                             WHERE tenant_id = ? AND id = ?'
                        )->execute([
                            $washRinseMinutes,
                            (int) $washer['id'],
                            $washRinseMinutes,
                            $defaultDryingMinutes,
                            (int) $washer['id'],
                            (int) $washer['id'],
                            $tenantId,
                            $id,
                        ]);
                        $pdo->commit();
                        if ($request->wantsJson()) {
                            return json_response(['success' => true, 'message' => 'Washing-rinsing timer started.']);
                        }
                        session_flash('success', 'Washing-rinsing timer started.');
                        return redirect(route('tenant.laundry-sales.index'));
                    }
                    if ($serviceKind === 'dry_only') {
                        $dryer = $this->fetchFirstAvailableMachineByKind($pdo, $tenantId, 'dryer');
                        if (! is_array($dryer) || (int) ($dryer['id'] ?? 0) < 1) {
                            throw new \RuntimeException('Ready for drying but no machine available.');
                        }
                        $markDryerRunning = $pdo->prepare(
                            'UPDATE laundry_machines
                             SET status = "running", current_order_id = ?, updated_at = NOW()
                             WHERE tenant_id = ? AND id = ? AND status = "available"'
                        );
                        $markDryerRunning->execute([$id, $tenantId, (int) $dryer['id']]);
                        if ($markDryerRunning->rowCount() < 1) {
                            throw new \RuntimeException('Ready for drying but no machine available.');
                        }
                        $usageQty = max(1, (int) ($order['wash_qty'] ?? 1));
                        $this->deductMachineCredits($pdo, $tenantId, [$dryer], $usageQty, $id);
                        $pdo->prepare(
                            'UPDATE laundry_orders
                             SET status = "washing_drying",
                                 track_machine_stage = "drying",
                                 wash_rinse_minutes = NULL,
                                 wash_rinse_machine_id = NULL,
                                 wash_rinse_started_at = NULL,
                                 wash_rinse_end_at = NULL,
                                 drying_minutes = ?,
                                 drying_machine_id = ?,
                                 drying_started_at = NOW(),
                                 drying_end_at = IF(? IS NULL, NULL, DATE_ADD(NOW(), INTERVAL ? MINUTE)),
                                 movement_completed_at = NULL,
                                 movement_last_error = NULL,
                                 machine_id = ?,
                                 washer_machine_id = NULL,
                                 dryer_machine_id = ?,
                                 machine_type = "dryer",
                                 updated_at = NOW()
                             WHERE tenant_id = ? AND id = ?'
                        )->execute([
                            $defaultDryingMinutes,
                            (int) $dryer['id'],
                            $defaultDryingMinutes,
                            $defaultDryingMinutes ?? 0,
                            (int) $dryer['id'],
                            (int) $dryer['id'],
                            $tenantId,
                            $id,
                        ]);
                        $pdo->commit();
                        if ($request->wantsJson()) {
                            return json_response(['success' => true, 'message' => 'Drying started.']);
                        }
                        session_flash('success', 'Drying started.');
                        return redirect(route('tenant.laundry-sales.index'));
                    }
                }
                $needsWasher = in_array($serviceKind, ['full_service', 'wash_only', 'rinse_only'], true);
                $needsDryer = in_array($serviceKind, ['full_service', 'dry_only'], true);
                if ($machineAssignmentEnabled) {
                    $washerMachineId = $needsWasher ? max(0, (int) $request->input('washer_machine_id', 0)) : 0;
                    $dryerMachineId = $needsDryer ? max(0, (int) $request->input('dryer_machine_id', 0)) : 0;
                    if ($needsWasher && $washerMachineId < 1) {
                        throw new \RuntimeException('Please select a washer before moving to Washing - Drying.');
                    }
                    if ($needsDryer && $dryerMachineId < 1) {
                        throw new \RuntimeException('Please select a dryer before moving to Washing - Drying.');
                    }
                    if ($washerMachineId > 0 && $dryerMachineId > 0 && $washerMachineId === $dryerMachineId) {
                        throw new \RuntimeException('Washer and dryer must be different machines.');
                    }
                    $washer = $needsWasher ? $this->fetchAvailableMachineByKind($pdo, $tenantId, $washerMachineId, 'washer') : null;
                    $dryer = $needsDryer ? $this->fetchAvailableMachineByKind($pdo, $tenantId, $dryerMachineId, 'dryer') : null;
                    if ($needsWasher && $washer === null) {
                        throw new \RuntimeException('Selected washer is not available or is not a washer.');
                    }
                    if ($needsDryer && $dryer === null) {
                        throw new \RuntimeException('Selected dryer is not available or is not a dryer.');
                    }
                    foreach ([$washer, $dryer] as $machine) {
                        if (! is_array($machine) || (int) ($machine['credit_required'] ?? 0) !== 1) {
                            continue;
                        }
                        if ((float) ($machine['credit_balance'] ?? 0) <= 0) {
                            $label = trim((string) ($machine['machine_label'] ?? 'Selected machine'));
                            throw new \RuntimeException($label.' has 0 credit. Please load machine credit before using it.');
                        }
                    }
                    $machineType = $this->resolveOrderMachineType($washer, $dryer);
                    $machineIdLegacy = $washerMachineId > 0 ? $washerMachineId : ($dryerMachineId > 0 ? $dryerMachineId : null);
                    $markRunning = $pdo->prepare(
                        'UPDATE laundry_machines
                         SET status = "running", current_order_id = ?, updated_at = NOW()
                         WHERE tenant_id = ? AND id = ? AND status = "available"'
                    );
                    foreach (array_unique(array_filter([$washerMachineId, $dryerMachineId])) as $mid) {
                        $markRunning->execute([$id, $tenantId, (int) $mid]);
                        if ($markRunning->rowCount() < 1) {
                            throw new \RuntimeException('Selected machine is no longer available.');
                        }
                    }
                    $this->deductMachineCredits($pdo, $tenantId, [$washer, $dryer], max(1, (int) ($order['wash_qty'] ?? 1)), $id);
                    $this->applyLoadCardUsage(
                        $pdo,
                        $tenantId,
                        $machineType,
                        $serviceKind,
                        max(1, (int) ($order['wash_qty'] ?? 1))
                    );
                    $pdo->prepare(
                        'UPDATE laundry_orders
                         SET machine_id = ?, washer_machine_id = ?, dryer_machine_id = ?, machine_type = ?, updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute([$machineIdLegacy, $washerMachineId > 0 ? $washerMachineId : null, $dryerMachineId > 0 ? $dryerMachineId : null, $machineType, $tenantId, $id]);
                }
            }

            if ($toStatus === 'open_ticket') {
                $pdo->prepare(
                    'UPDATE laundry_machines
                     SET status = "available", current_order_id = NULL, updated_at = NOW()
                     WHERE tenant_id = ? AND current_order_id = ?'
                )->execute([$tenantId, $id]);
            }

            $nextStatus = $toStatus;
            $paidFlag = (string) ($order['payment_status'] ?? '');
            if ($toStatus === 'open_ticket') {
                if ($paidFlag === 'paid') {
                    $nextStatus = 'paid';
                }
            }
            if ($paidFlag === 'paid') {
                $pdo->prepare(
                    'UPDATE laundry_orders
                     SET status = ?, payment_status = "paid", updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute([$nextStatus, $tenantId, $id]);
            } else {
                $pdo->prepare(
                    'UPDATE laundry_orders
                     SET status = ?, payment_method = "pending", payment_status = "unpaid", updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute([$nextStatus, $tenantId, $id]);
            }

            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 422);
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.laundry-sales.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        if ($request->wantsJson()) {
            return json_response([
                'success' => true,
                'message' => 'Status updated.',
            ]);
        }

        session_flash('success', 'Status updated.');

        return redirect(route('tenant.laundry-sales.index'));
    }

    public function completeTransaction(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        if (! $this->isLaundryStatusTrackingEnabled($pdo, $tenantId)) {
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => 'Laundry status tracking is disabled in Branch Settings.'], 422);
            }
            session_flash('errors', ['Laundry status tracking is disabled in Branch Settings.']);

            return redirect(route('tenant.laundry-sales.index'));
        }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT id, status, payment_status, is_void
                 FROM laundry_orders
                 WHERE tenant_id = ? AND id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $st->execute([$tenantId, $id]);
            $order = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if (! $order) {
                throw new \RuntimeException('Transaction not found.');
            }
            if ((int) ($order['is_void'] ?? 0) === 1 || (string) ($order['status'] ?? '') === 'void') {
                throw new \RuntimeException('VOID transactions are read-only.');
            }
            if (! in_array((string) ($order['status'] ?? ''), ['washing_drying', 'running'], true)) {
                throw new \RuntimeException('This transaction is not in Washing - Drying.');
            }

            $alreadyPaid = (string) ($order['payment_status'] ?? '') === 'paid';
            $pdo->prepare(
                'UPDATE laundry_orders
                 SET status = ?, payment_method = IF(? = 1, payment_method, "pending"), payment_status = IF(? = 1, "paid", "unpaid"), updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([$alreadyPaid ? 'paid' : 'open_ticket', $alreadyPaid ? 1 : 0, $alreadyPaid ? 1 : 0, $tenantId, $id]);
            $pdo->prepare(
                'UPDATE laundry_machines
                 SET status = "available", current_order_id = NULL, updated_at = NOW()
                 WHERE tenant_id = ? AND current_order_id = ?'
            )->execute([$tenantId, $id]);
            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 422);
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.laundry-sales.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        if ($request->wantsJson()) {
            return json_response(['success' => true, 'message' => 'Status updated.']);
        }
        session_flash('success', 'Status updated.');

        return redirect(route('tenant.laundry-sales.index'));
    }

    public function movementTick(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        if (! $this->isLaundryStatusTrackingEnabled($pdo, $tenantId) || ! $this->isTrackMachineMovementEnabled($pdo, $tenantId)) {
            return json_response(['success' => true, 'orders' => []]);
        }
        $this->processTrackMachineMovementTimers($pdo, $tenantId);
        try {
            $st = $pdo->prepare(
                'SELECT id, track_machine_stage, payment_status, wash_rinse_end_at, drying_end_at
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND status = "washing_drying"
                   AND track_machine_stage IN ("washing_rinsing", "drying", "drying_waiting_machine", "drying_done")
                   AND COALESCE(is_void, 0) = 0'
            );
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $completedSt = $pdo->prepare(
                'SELECT id, reference_code, payment_status, movement_completed_at
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND track_machine_stage = "completed"
                   AND movement_completed_at IS NOT NULL
                   AND movement_completed_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                 ORDER BY movement_completed_at DESC
                 LIMIT 25'
            );
            $completedSt->execute([$tenantId]);
            $completedRows = $completedSt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return json_response([
                'success' => true,
                'orders' => $rows,
                'completed_orders' => $completedRows,
            ]);
        } catch (\Throwable $e) {
            return json_response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function payTransaction(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $paymentMethod = strtolower(trim((string) $request->input('payment_method', '')));
        $allowedPayment = ['cash', 'gcash', 'paymaya', 'online_banking', 'qr_payment', 'card', 'split_payment'];
        $amountTendered = max(0.0, (float) $request->input('amount_tendered', 0));
        $splitCashAmount = round(max(0.0, (float) $request->input('split_cash_amount', 0)), 4);
        $splitOnlineAmount = round(max(0.0, (float) $request->input('split_online_amount', 0)), 4);
        $splitOnlineMethod = strtolower(trim((string) $request->input('split_online_method', '')));
        if (! in_array($splitOnlineMethod, ['gcash', 'paymaya', 'online_banking', 'qr_payment', 'card'], true)) {
            $splitOnlineMethod = '';
        }
        $discountPercentage = (float) $request->input('discount_percentage', 0);
        $discountPercentage = max(0.0, min(100.0, $discountPercentage));
        $completedWithoutPayment = false;
        $isFullyPaid = false;
        $remainingBalance = 0.0;

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT id, status, total_amount, payment_status, amount_tendered, customer_id, order_type, is_free, is_reward, is_void, wash_qty, dry_minutes
                 FROM laundry_orders
                 WHERE tenant_id = ? AND id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $st->execute([$tenantId, $id]);
            $order = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if (! $order) {
                throw new \RuntimeException('Transaction not found.');
            }
            if ((int) ($order['is_void'] ?? 0) === 1 || (string) ($order['status'] ?? '') === 'void') {
                throw new \RuntimeException('VOID transactions are read-only.');
            }
            $currStatus = (string) ($order['status'] ?? '');
            $isNoPaymentMode = (int) ($order['is_free'] ?? 0) === 1 || (int) ($order['is_reward'] ?? 0) === 1;
            $payStatus = (string) ($order['payment_status'] ?? 'paid');
            if ($payStatus === 'paid') {
                if ($isNoPaymentMode) {
                    if ($request->wantsJson()) {
                        return json_response(['success' => true, 'message' => '']);
                    }
                    session_flash('success', 'Transaction already marked as Paid.');

                    return redirect(route('tenant.laundry-sales.index'));
                }
                throw new \RuntimeException('This transaction is already paid.');
            }
            if ($isNoPaymentMode) {
                if (! in_array($currStatus, ['pending', 'washing_drying', 'running', 'open_ticket', 'paid'], true)) {
                    throw new \RuntimeException('This transaction cannot be moved to Paid.');
                }
            } elseif (! in_array($currStatus, ['open_ticket', 'paid'], true)) {
                throw new \RuntimeException('Payment can only be recorded for Finishing - To Be Picked Up transactions.');
            }

            if ($isNoPaymentMode) {
                $paymentMethod = (int) ($order['is_reward'] ?? 0) === 1 ? 'reward' : 'free';
                $amountTendered = 0.0;
                $discountPercentage = 0.0;
                $splitCashAmount = 0.0;
                $splitOnlineAmount = 0.0;
                $splitOnlineMethod = '';
            } elseif (! in_array($paymentMethod, $allowedPayment, true)) {
                throw new \RuntimeException('Select a payment method.');
            }

            $total = (float) ($order['total_amount'] ?? 0);
            $discountAmount = round($total * ($discountPercentage / 100), 4);
            $discountedTotal = round(max(0.0, $total - $discountAmount), 4);
            if (! $isNoPaymentMode && $amountTendered <= 0.0) {
                throw new \RuntimeException('Enter an amount paid greater than 0.');
            }
            if (! $isNoPaymentMode && $paymentMethod === 'split_payment') {
                if ($splitCashAmount <= 0 && $splitOnlineAmount <= 0) {
                    throw new \RuntimeException('Enter split payment amounts.');
                }
                if ($splitOnlineAmount > 0 && $splitOnlineMethod === '') {
                    throw new \RuntimeException('Select an online payment method for split payment.');
                }
                $splitTotal = round($splitCashAmount + $splitOnlineAmount, 4);
                if ($splitCashAmount <= 0.000001 && $splitTotal - $discountedTotal > 0.01) {
                    throw new \RuntimeException('Split payment cannot exceed the service total unless there is a cash part for change.');
                }
            } elseif ($paymentMethod !== 'split_payment') {
                $splitCashAmount = 0.0;
                $splitOnlineAmount = 0.0;
                $splitOnlineMethod = '';
            }
            if ($isNoPaymentMode) {
                $discountAmount = 0.0;
                $discountedTotal = 0.0;
            }

            $alreadyPaidRaw = max(0.0, (float) ($order['amount_tendered'] ?? 0));
            $alreadyPaid = min($alreadyPaidRaw, $discountedTotal);
            $paidNow = min($amountTendered, max(0.0, $discountedTotal - $alreadyPaid));
            $paidTotal = min($discountedTotal, $alreadyPaid + max(0.0, $amountTendered));
            $remainingBalance = max(0.0, round($discountedTotal - $paidTotal, 4));
            $isFullyPaid = $remainingBalance <= 0.000001;
            $change = $isFullyPaid ? round(max(0.0, ($alreadyPaid + $amountTendered) - $discountedTotal), 4) : 0.0;
            $nextStatus = $isFullyPaid ? 'paid' : 'open_ticket';
            $nextPaymentStatus = $isFullyPaid ? 'paid' : 'unpaid';

            $setParts = [
                'status = ?',
                'payment_method = ?',
                'payment_status = ?',
                'amount_tendered = ?',
                'change_amount = ?',
                'total_amount = ?',
                'split_cash_amount = ?',
                'split_online_amount = ?',
                'split_online_method = ?',
                'updated_at = NOW()',
            ];
            $params = [
                $nextStatus,
                $paymentMethod,
                $nextPaymentStatus,
                $paidTotal,
                $change,
                $discountedTotal,
                $splitCashAmount,
                $splitOnlineAmount,
                $splitOnlineMethod !== '' ? $splitOnlineMethod : null,
            ];
            if ($this->hasLaundryOrdersDiscountPercentage($pdo)) {
                $setParts[] = 'discount_percentage = ?';
                $params[] = $discountPercentage;
            }
            if ($this->hasLaundryOrdersDiscountAmount($pdo)) {
                $setParts[] = 'discount_amount = ?';
                $params[] = $discountAmount;
            }
            $params[] = $tenantId;
            $params[] = $id;
            $sql = 'UPDATE laundry_orders SET '.implode(', ', $setParts).' WHERE tenant_id = ? AND id = ?';
            $pdo->prepare($sql)->execute($params);
            if ($isNoPaymentMode) {
                $pdo->prepare(
                    'UPDATE laundry_machines
                     SET status = "available", current_order_id = NULL, updated_at = NOW()
                     WHERE tenant_id = ? AND current_order_id = ?'
                )->execute([$tenantId, $id]);
            }

            if ($isFullyPaid && (int) ($order['is_free'] ?? 0) !== 1 && (int) ($order['is_reward'] ?? 0) !== 1) {
                $otDef = $this->fetchOrderTypeByCode($pdo, $tenantId, (string) ($order['order_type'] ?? ''));
                $this->applyRewardsCounter($pdo, $tenantId, (int) ($order['customer_id'] ?? 0) ?: null, $otDef, $id, (int) (Auth::user()['id'] ?? 0), $order);
            } else {
                $completedWithoutPayment = true;
            }

            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 422);
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.laundry-sales.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 500);
            }
            throw $e;
        }

        if (! empty($isFullyPaid)) {
            $successMessage = $completedWithoutPayment ? 'Transaction marked as paid without payment.' : 'Payment recorded.';
        } else {
            $successMessage = 'Partial payment recorded. Remaining balance: '.number_format(max(0.0, $remainingBalance ?? 0.0), 2, '.', '').'.';
        }
        if ($request->wantsJson()) {
            return json_response([
                'success' => true,
                'message' => $successMessage,
                'payment_status' => ! empty($isFullyPaid) ? 'paid' : 'unpaid',
                'status' => ! empty($isFullyPaid) ? 'paid' : 'open_ticket',
                'paid_total' => max(0.0, (float) ($paidTotal ?? 0.0)),
                'remaining_balance' => max(0.0, (float) ($remainingBalance ?? 0.0)),
            ]);
        }

        session_flash('success', $successMessage);

        return redirect(route('tenant.laundry-sales.index'));
    }

    public function voidTransaction(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $user = Auth::user();
        $role = (string) ($user['role'] ?? '');
        if ($role !== 'tenant_admin') {
            $msg = 'Only tenant admin can void transactions.';
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $msg], 403);
            }
            session_flash('errors', [$msg]);

            return redirect(route('tenant.laundry-sales.index'));
        }
        $userId = (int) ($user['id'] ?? 0);
        $reason = trim((string) $request->input('void_reason', ''));
        if ($reason === '') {
            session_flash('errors', ['Void reason is required.']);

            return redirect(route('tenant.laundry-sales.index'));
        }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT id, status, is_void, payment_status, customer_id, is_free, is_reward
                 FROM laundry_orders
                 WHERE tenant_id = ? AND id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $st->execute([$tenantId, $id]);
            $order = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($order)) {
                throw new \RuntimeException('Transaction not found.');
            }
            if ((int) ($order['is_void'] ?? 0) === 1 || (string) ($order['status'] ?? '') === 'void') {
                throw new \RuntimeException('Transaction is already void.');
            }
            $this->reverseRewardEarnForVoidedOrderIfApplicable($pdo, $tenantId, $id, $order, $userId);
            $this->restoreInventoryForVoidedOrder($pdo, $tenantId, $id, $userId > 0 ? $userId : null);
            $pdo->prepare(
                'UPDATE laundry_machines
                 SET status = "available", current_order_id = NULL, updated_at = NOW()
                 WHERE tenant_id = ? AND current_order_id = ?'
            )->execute([$tenantId, $id]);
            $pdo->prepare(
                'UPDATE laundry_orders
                 SET is_void = 1, status = "void", voided_by = ?, voided_at = NOW(), void_reason = ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([$userId > 0 ? $userId : null, $reason, $tenantId, $id]);
            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.laundry-sales.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        session_flash('success', 'Transaction marked VOID.');
        ActivityLogger::log(
            $tenantId,
            $userId,
            $role !== '' ? $role : 'tenant_admin',
            'laundry_sales',
            'void',
            $request,
            'Marked transaction as void.',
            ['order_id' => $id, 'reason' => mb_substr($reason, 0, 255)]
        );

        return redirect(route('tenant.laundry-sales.index'));
    }

    public function salesOrderDetail(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $st = $pdo->prepare(
            'SELECT o.*, c.name AS customer_name,
                    m.machine_label AS legacy_machine_label, m.machine_kind AS legacy_machine_kind,
                    mw.machine_label AS washer_machine_label, mw.machine_kind AS washer_kind,
                    md.machine_label AS dryer_machine_label, md.machine_kind AS dryer_kind,
                    ot.label AS order_type_label, ot.service_kind AS order_type_service_kind,
                    ot.supply_block AS order_type_supply_block
             FROM laundry_orders o
             LEFT JOIN laundry_customers c ON c.id = o.customer_id
             LEFT JOIN laundry_machines m ON m.id = o.machine_id
             LEFT JOIN laundry_machines mw ON mw.id = o.washer_machine_id
             LEFT JOIN laundry_machines md ON md.id = o.dryer_machine_id
             LEFT JOIN laundry_order_types ot ON ot.tenant_id = o.tenant_id AND ot.code = o.order_type
             WHERE o.tenant_id = ? AND o.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $order = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($order)) {
            return json_response(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        $inclusionMap = [
            'detergent' => 'inclusion_detergent_item_id',
            'fabcon' => 'inclusion_fabcon_item_id',
            'bleach' => 'inclusion_bleach_item_id',
        ];
        $inclusions = [];
        foreach ($inclusionMap as $label => $col) {
            $iid = (int) ($order[$col] ?? 0);
            if ($iid < 1) {
                $inclusions[$label] = null;

                continue;
            }
            $it = $pdo->prepare(
                'SELECT id, name, category FROM laundry_inventory_items WHERE tenant_id = ? AND id = ? LIMIT 1'
            );
            $it->execute([$tenantId, $iid]);
            $row = $it->fetch(PDO::FETCH_ASSOC);
            $inclusions[$label] = is_array($row) ? $row : ['id' => $iid, 'name' => 'Unknown item', 'category' => ''];
        }

        $addonsSt = $pdo->prepare(
            'SELECT item_name, quantity, unit_price, total_price
             FROM laundry_order_add_ons
             WHERE tenant_id = ? AND order_id = ?
             ORDER BY id ASC'
        );
        $addonsSt->execute([$tenantId, $id]);
        $addOns = $addonsSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return json_response([
            'success' => true,
            'order' => $order,
            'inclusions' => $inclusions,
            'add_ons' => $addOns,
        ]);
    }

    public function customersIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $st = $pdo->prepare(
            'SELECT c.*,
                    COALESCE(cp.points_balance, 0) AS rewards_balance,
                    COALESCE(cp.lifetime_earned, 0) AS lifetime_rewards_earned,
                    COALESCE(cp.lifetime_redeemed, 0) AS lifetime_rewards_redeemed,
                    COUNT(o.id) AS visit_count,
                    COALESCE(SUM(o.total_amount), 0) AS total_spent
             FROM laundry_customers c
             LEFT JOIN laundry_customer_points cp ON cp.customer_id = c.id AND cp.tenant_id = c.tenant_id
             LEFT JOIN laundry_orders o ON o.customer_id = c.id AND o.tenant_id = c.tenant_id
               AND COALESCE(o.is_void, 0) = 0
               AND o.status <> "void"
               AND o.status = "paid" AND o.payment_status = "paid"
             WHERE c.tenant_id = ?
             GROUP BY c.id
             ORDER BY visit_count DESC, total_spent DESC, c.name ASC'
        );
        $st->execute([$tenantId]);

        return view_page('Customer Profile', 'tenant.laundry.customers', [
            'customers' => $st->fetchAll(PDO::FETCH_ASSOC),
            'premium_trial_browse_lock' => Auth::isTenantFreePlanRestricted(Auth::user()),
            'reward_system_active' => $this->rewardProgramIsActive($pdo, $tenantId),
        ]);
    }

    public function customersStore(Request $request): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: customer profile actions are not available in Free Mode.']);

            return redirect(route('tenant.customers.index'));
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $name = trim((string) $request->input('name'));
        $contact = trim((string) $request->input('contact'));
        $email = trim((string) $request->input('email'));
        $birthday = trim((string) $request->input('birthday'));
        if ($name === '') {
            session_flash('errors', ['Customer name is required.']);

            return redirect(route('tenant.customers.index'));
        }
        if ($contact === '') {
            session_flash('errors', ['Contact is required.']);

            return redirect(route('tenant.customers.index'));
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session_flash('errors', ['A valid email is required.']);

            return redirect(route('tenant.customers.index'));
        }
        $birthdayValue = $birthday !== '' ? $birthday : null;

        $pdo->prepare(
            'INSERT INTO laundry_customers (tenant_id, name, contact, email, birthday, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([$tenantId, $name, $contact !== '' ? $contact : null, $email !== '' ? $email : null, $birthdayValue]);

        session_flash('success', 'Customer saved.');

        return redirect(route('tenant.customers.index'));
    }

    public function customersStoreFromKiosk(Request $request): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            return json_response(['success' => false, 'message' => 'Premium: customer profile actions are not available in Free Mode.'], 403);
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $name = trim((string) $request->input('name'));
        $contact = trim((string) $request->input('contact'));
        $email = trim((string) $request->input('email'));
        $birthday = trim((string) $request->input('birthday'));
        if ($name === '') {
            return json_response(['success' => false, 'message' => 'Customer name is required.'], 422);
        }
        if ($contact === '') {
            return json_response(['success' => false, 'message' => 'Contact is required.'], 422);
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json_response(['success' => false, 'message' => 'A valid email is required.'], 422);
        }
        $birthdayValue = $birthday !== '' ? $birthday : null;

        $pdo->prepare(
            'INSERT INTO laundry_customers (tenant_id, name, contact, email, birthday, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([$tenantId, $name, $contact !== '' ? $contact : null, $email !== '' ? $email : null, $birthdayValue]);
        $customerId = (int) $pdo->lastInsertId();

        return json_response([
            'success' => true,
            'message' => 'Customer saved.',
            'customer' => [
                'id' => $customerId,
                'name' => $name,
                'contact' => $contact,
                'email' => $email,
                'birthday' => $birthdayValue,
                'rewards_balance' => 0,
            ],
        ]);
    }

    public function updateBluetoothPrintPreference(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        if ($tenantId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid tenant context.'], 422);
        }
        $enabled = $request->boolean('enable_bluetooth_print');
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            $enabled = false;
        }
        try {
            $this->persistBranchBluetoothPrintConfig($pdo, $tenantId, $enabled);
        } catch (\Throwable) {
            return json_response(['success' => false, 'message' => 'Could not save Bluetooth printing setting.'], 500);
        }

        return json_response([
            'success' => true,
            'message' => 'Bluetooth printing setting saved.',
            'enable_bluetooth_print' => $enabled ? 1 : 0,
        ]);
    }

    public function updateTrackGasulUsagePreference(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $actor = Auth::user();
        if (($actor['role'] ?? '') !== 'tenant_admin') {
            return json_response(['success' => false, 'message' => 'Only the store owner can update this setting.'], 403);
        }
        if ($tenantId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid tenant context.'], 422);
        }
        $enabled = $request->boolean('track_gasul_usage');
        try {
            $this->persistBranchTrackGasulUsageConfig($pdo, $tenantId, $enabled);
            $hasKioskAutomationFields = $request->input('kiosk_inclusion_autofill_lock', null) !== null
                || $request->input('kiosk_inclusion_autofill_editable', null) !== null
                || $request->input('kiosk_fold_autofill_free_fold', null) !== null
                || $request->input('kiosk_fold_autofill_fold_with_price', null) !== null
                || $request->input('kiosk_autofill_order_type_codes', null) !== null;
            if ($hasKioskAutomationFields) {
                $this->persistBranchKioskAutomationSettings($pdo, $tenantId, $this->parseKioskAutomationSettingsFromRequest($request));
            }
        } catch (\Throwable) {
            return json_response(['success' => false, 'message' => 'Could not save Track Gasul Usage setting.'], 500);
        }
        $settings = $this->getBranchKioskAutomationSettings($pdo, $tenantId);

        return json_response([
            'success' => true,
            'message' => 'Track Gasul Usage setting saved.',
            'track_gasul_usage' => $enabled ? 1 : 0,
            'kiosk_automation_settings' => $settings,
        ]);
    }

    public function updateTransactionOrderDate(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $orderId = max(0, (int) $id);
        $actor = Auth::user();
        if (($actor['role'] ?? '') !== 'tenant_admin') {
            return json_response(['success' => false, 'message' => 'Only the store owner can edit order date/time.'], 403);
        }
        if ($orderId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid transaction selected.'], 422);
        }
        if (! $this->isEditableOrderDateEnabled($pdo, $tenantId)) {
            return json_response(['success' => false, 'message' => 'Editable Order Date is disabled.'], 422);
        }

        $raw = trim((string) $request->input('order_datetime', ''));
        if ($raw === '') {
            return json_response(['success' => false, 'message' => 'Date and time are required.'], 422);
        }
        $normalized = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized) !== 1) {
            return json_response(['success' => false, 'message' => 'Invalid date/time format.'], 422);
        }
        $ts = strtotime($normalized);
        if ($ts === false) {
            return json_response(['success' => false, 'message' => 'Invalid date/time value.'], 422);
        }
        $safeValue = date('Y-m-d H:i:s', $ts);

        $st = $pdo->prepare('SELECT id, reference_code, created_at FROM laundry_orders WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row)) {
            return json_response(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        $pdo->prepare('UPDATE laundry_orders SET created_at = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ? LIMIT 1')
            ->execute([$safeValue, $tenantId, $orderId]);

        ActivityLogger::log(
            $tenantId,
            (int) ($actor['id'] ?? 0),
            (string) ($actor['role'] ?? 'tenant_admin'),
            'laundry_sales',
            'update_order_datetime',
            $request,
            'Updated order date/time from Daily Sales.',
            [
                'order_id' => $orderId,
                'reference_code' => (string) ($row['reference_code'] ?? ''),
                'old_created_at' => (string) ($row['created_at'] ?? ''),
                'new_created_at' => $safeValue,
            ]
        );

        return json_response([
            'success' => true,
            'message' => 'Order date/time updated.',
            'order_id' => $orderId,
            'created_at' => $safeValue,
            'order_datetime_local' => date('Y-m-d\TH:i', $ts),
        ]);
    }

    public function customersTransactions(Request $request, string $id): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $customerId = max(0, (int) $id);
        if ($customerId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid customer selected.'], 422);
        }

        $cst = $pdo->prepare('SELECT id, name FROM laundry_customers WHERE tenant_id = ? AND id = ? LIMIT 1');
        $cst->execute([$tenantId, $customerId]);
        $customer = $cst->fetch(PDO::FETCH_ASSOC);
        if (! is_array($customer)) {
            return json_response(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $ordersSt = $pdo->prepare(
            'SELECT
                o.id,
                o.reference_code,
                o.created_at,
                o.updated_at,
                o.order_type,
                o.machine_type,
                o.machine_id,
                o.washer_machine_id,
                o.dryer_machine_id,
                o.wash_qty,
                o.dry_minutes,
                o.service_weight,
                o.actual_weight_kg,
                o.excess_weight_kg,
                o.excess_weight_fee_amount,
                o.subtotal,
                o.add_on_total,
                o.discount_percentage,
                o.discount_amount,
                o.total_amount,
                o.payment_method,
                o.payment_status,
                o.split_cash_amount,
                o.split_online_amount,
                o.split_online_method,
                o.include_fold_service,
                o.status,
                o.is_free,
                o.is_reward
             FROM laundry_orders o
             WHERE o.tenant_id = ? AND o.customer_id = ?
               AND COALESCE(o.is_void, 0) = 0
             ORDER BY o.created_at DESC, o.id DESC'
        );
        $ordersSt->execute([$tenantId, $customerId]);
        $orders = $ordersSt->fetchAll(PDO::FETCH_ASSOC);

        $addonSt = $pdo->prepare(
            'SELECT order_id, item_name, quantity, unit_price, total_price
             FROM laundry_order_add_ons
             WHERE tenant_id = ? AND order_id = ?
             ORDER BY id ASC'
        );

        $payload = [];
        foreach ($orders as $row) {
            $orderId = (int) ($row['id'] ?? 0);
            if ($orderId > 0) {
                $addonSt->execute([$tenantId, $orderId]);
                $addons = $addonSt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $addons = [];
            }
            $payload[] = [
                'id' => $orderId,
                'reference_code' => (string) ($row['reference_code'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'order_type' => (string) ($row['order_type'] ?? ''),
                'machine_type' => (string) ($row['machine_type'] ?? ''),
                'machine_id' => (int) ($row['machine_id'] ?? 0),
                'washer_machine_id' => (int) ($row['washer_machine_id'] ?? 0),
                'dryer_machine_id' => (int) ($row['dryer_machine_id'] ?? 0),
                'wash_qty' => (float) ($row['wash_qty'] ?? 0),
                'dry_minutes' => (int) ($row['dry_minutes'] ?? 0),
                'service_weight' => (float) ($row['service_weight'] ?? 0),
                'actual_weight_kg' => (float) ($row['actual_weight_kg'] ?? 0),
                'excess_weight_kg' => (float) ($row['excess_weight_kg'] ?? 0),
                'excess_weight_fee_amount' => (float) ($row['excess_weight_fee_amount'] ?? 0),
                'subtotal' => (float) ($row['subtotal'] ?? 0),
                'add_on_total' => (float) ($row['add_on_total'] ?? 0),
                'discount_percentage' => (float) ($row['discount_percentage'] ?? 0),
                'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                'total_amount' => (float) ($row['total_amount'] ?? 0),
                'payment_method' => (string) ($row['payment_method'] ?? ''),
                'payment_status' => (string) ($row['payment_status'] ?? ''),
                'split_cash_amount' => (float) ($row['split_cash_amount'] ?? 0),
                'split_online_amount' => (float) ($row['split_online_amount'] ?? 0),
                'split_online_method' => (string) ($row['split_online_method'] ?? ''),
                'include_fold_service' => (int) ($row['include_fold_service'] ?? 0) === 1,
                'status' => (string) ($row['status'] ?? ''),
                'is_free' => (int) ($row['is_free'] ?? 0) === 1,
                'is_reward' => (int) ($row['is_reward'] ?? 0) === 1,
                'add_ons' => $addons,
            ];
        }

        return json_response([
            'success' => true,
            'customer' => [
                'id' => (int) ($customer['id'] ?? 0),
                'name' => (string) ($customer['name'] ?? ''),
            ],
            'transactions' => $payload,
        ]);
    }

    public function customersUpdate(Request $request, string $id): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: customer profile actions are not available in Free Mode.']);

            return redirect(route('tenant.customers.index'));
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $customerId = max(0, (int) $id);

        $name = trim((string) $request->input('name'));
        $contact = trim((string) $request->input('contact'));
        $email = trim((string) $request->input('email'));
        $birthday = trim((string) $request->input('birthday'));
        if ($customerId < 1 || $name === '') {
            session_flash('errors', ['Customer name is required.']);

            return redirect(route('tenant.customers.index'));
        }
        if ($contact === '') {
            session_flash('errors', ['Contact is required.']);

            return redirect(route('tenant.customers.index'));
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session_flash('errors', ['A valid email is required.']);

            return redirect(route('tenant.customers.index'));
        }

        $birthdayValue = $birthday !== '' ? $birthday : null;
        $pdo->prepare(
            'UPDATE laundry_customers
             SET name = ?, contact = ?, email = ?, birthday = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        )->execute([
            $name,
            $contact !== '' ? $contact : null,
            $email !== '' ? $email : null,
            $birthdayValue,
            $tenantId,
            $customerId,
        ]);

        session_flash('success', 'Customer updated.');

        return redirect(route('tenant.customers.index'));
    }

    public function customersAdjustRewards(Request $request, string $id): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: rewards actions are not available in Free Mode.']);

            return redirect(route('tenant.customers.index'));
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $customerId = max(0, (int) $id);
        $adjustType = strtolower(trim((string) $request->input('adjust_type', 'add')));
        if (! in_array($adjustType, ['add', 'deduct'], true)) {
            $adjustType = 'add';
        }
        $count = round((float) $request->input('points_count', 0), 4);
        $delta = $adjustType === 'deduct' ? -$count : $count;
        if ($customerId < 1) {
            session_flash('errors', ['Invalid customer selected.']);

            return redirect(route('tenant.customers.index'));
        }
        if ($count < 0.0001) {
            session_flash('errors', ['Enter a count greater than zero.']);

            return redirect(route('tenant.customers.index'));
        }
        if ($count > 1000000) {
            session_flash('errors', ['Adjustment is too large.']);

            return redirect(route('tenant.customers.index'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $cst = $pdo->prepare('SELECT id, name FROM laundry_customers WHERE tenant_id = ? AND id = ? LIMIT 1 FOR UPDATE');
            $cst->execute([$tenantId, $customerId]);
            $customer = $cst->fetch(PDO::FETCH_ASSOC);
            if (! is_array($customer)) {
                throw new \RuntimeException('Customer not found.');
            }

            $pdo->prepare(
                'INSERT INTO laundry_customer_points (tenant_id, customer_id, points_balance, lifetime_earned, lifetime_redeemed, created_at, updated_at)
                 VALUES (?, ?, 0, 0, 0, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE updated_at = updated_at'
            )->execute([$tenantId, $customerId]);

            $ptSt = $pdo->prepare(
                'SELECT id, points_balance, lifetime_earned, lifetime_redeemed
                 FROM laundry_customer_points
                 WHERE tenant_id = ? AND customer_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $ptSt->execute([$tenantId, $customerId]);
            $pt = $ptSt->fetch(PDO::FETCH_ASSOC);
            if (! is_array($pt)) {
                throw new \RuntimeException('Customer reward row was not found.');
            }

            $before = (float) ($pt['points_balance'] ?? 0);
            $after = round($before + $delta, 4);
            if ($after < -0.0001) {
                throw new \RuntimeException('Cannot deduct more than the current reward count.');
            }
            $after = max(0.0, $after);
            $earned = (float) ($pt['lifetime_earned'] ?? 0);
            $redeemed = (float) ($pt['lifetime_redeemed'] ?? 0);
            if ($delta > 0) {
                $earned = round($earned + $delta, 4);
            } else {
                $redeemed = round($redeemed + abs($delta), 4);
            }

            $pdo->prepare(
                'UPDATE laundry_customer_points
                 SET points_balance = ?, lifetime_earned = ?, lifetime_redeemed = ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([$after, $earned, $redeemed, (int) ($pt['id'] ?? 0)]);

            try {
                $pdo->prepare(
                    'INSERT INTO laundry_reward_events (tenant_id, customer_id, order_id, event_type, points_delta, balance_after, reward_config_id, reward_order_type_code, actor_user_id, created_at)
                     VALUES (?, ?, NULL, "manual_adjustment", ?, ?, NULL, NULL, ?, NOW())'
                )->execute([$tenantId, $customerId, $delta, $after, $userId > 0 ? $userId : null]);
            } catch (\Throwable) {
                // Keep adjustment working even if reward events table is unavailable.
            }
            $pdo->commit();

            $customerName = (string) ($customer['name'] ?? 'Customer');
            $sign = $delta > 0 ? '+' : '';
            session_flash('success', 'Reward count adjusted for '.$customerName.' ('.$sign.number_format($delta, 2).').');
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', [$e->getMessage()]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return redirect(route('tenant.customers.index'));
    }

    public function customersDestroy(Request $request, string $id): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: customer profile actions are not available in Free Mode.']);

            return redirect(route('tenant.customers.index'));
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);
        $customerId = max(0, (int) $id);
        if ($customerId < 1) {
            session_flash('errors', ['Invalid customer selected.']);

            return redirect(route('tenant.customers.index'));
        }

        $custSt = $pdo->prepare('SELECT id, name FROM laundry_customers WHERE tenant_id = ? AND id = ? LIMIT 1');
        $custSt->execute([$tenantId, $customerId]);
        $customer = $custSt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (! is_array($customer)) {
            session_flash('errors', ['Customer not found.']);
            return redirect(route('tenant.customers.index'));
        }
        $pdo->prepare(
            'UPDATE laundry_orders
             SET customer_id = NULL, updated_at = NOW()
             WHERE tenant_id = ? AND customer_id = ?'
        )->execute([$tenantId, $customerId]);
        $pdo->prepare('DELETE FROM laundry_customer_points WHERE tenant_id = ? AND customer_id = ?')
            ->execute([$tenantId, $customerId]);
        $pdo->prepare('DELETE FROM laundry_customers WHERE tenant_id = ? AND id = ?')
            ->execute([$tenantId, $customerId]);
        ActivityLogger::log(
            $tenantId,
            $actorId,
            (string) ($actor['role'] ?? 'tenant_admin'),
            'customers',
            'destroy',
            $request,
            'Deleted customer profile.',
            ['customer_id' => $customerId, 'customer_name' => (string) ($customer['name'] ?? '')]
        );

        session_flash('success', 'Customer deleted.');

        return redirect(route('tenant.customers.index'));
    }

    public function attendanceIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $attendanceLocked = Auth::isTenantFreePlanRestricted(Auth::user());
        $from = trim((string) $request->query('from', date('Y-m-d')));
        $to = trim((string) $request->query('to', date('Y-m-d')));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = date('Y-m-d');
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $hasUserWorkingHours = $this->hasColumn($pdo, 'users', 'working_hours_per_day');
        $hasBranchPayrollHours = $this->hasColumn($pdo, 'laundry_branch_configs', 'payroll_hours_per_day');
        $workingHoursExpr = $hasUserWorkingHours
            ? ($hasBranchPayrollHours ? 'COALESCE(u.working_hours_per_day, bc.payroll_hours_per_day, 8.00)' : 'COALESCE(u.working_hours_per_day, 8.00)')
            : ($hasBranchPayrollHours ? 'COALESCE(bc.payroll_hours_per_day, 8.00)' : '8.00');
        $st = $pdo->prepare(
            'SELECT tl.id, tl.user_id, u.name AS staff_name, DATE(tl.clock_in_at) AS attendance_date,
                    tl.clock_in_at, tl.clock_out_at, tl.clock_in_photo_path, tl.clock_out_photo_path, tl.note,
                    tl.is_edited, tl.edit_reason, tl.overtime_status,
                    '.$workingHoursExpr.' AS working_hours_per_day
             FROM laundry_time_logs tl
             INNER JOIN users u ON u.id = tl.user_id
             LEFT JOIN laundry_branch_configs bc ON bc.tenant_id = tl.tenant_id
             WHERE tl.tenant_id = ? AND tl.clock_in_at BETWEEN ? AND ?
             ORDER BY tl.clock_in_at DESC
             LIMIT 500'
        );
        $st->execute([$tenantId, $from.' 00:00:00', $to.' 23:59:59']);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $in = strtotime((string) ($row['clock_in_at'] ?? ''));
            $out = strtotime((string) ($row['clock_out_at'] ?? ''));
            $secs = $in !== false ? max(0, (($out !== false ? $out : time()) - $in)) : 0;
            $hoursPerDayRow = max(1.0, min(24.0, (float) ($row['working_hours_per_day'] ?? 8)));
            $classification = $this->classifyAttendanceSeconds($secs, $hoursPerDayRow);
            $otSeconds = max(0, $secs - (int) round($hoursPerDayRow * 3600));
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'attendance_date' => (string) ($row['attendance_date'] ?? ''),
                'staff_name' => (string) ($row['staff_name'] ?? ''),
                'time_in' => (string) ($row['clock_in_at'] ?? ''),
                'time_out' => (string) ($row['clock_out_at'] ?? ''),
                'clock_in_photo_path' => (string) ($row['clock_in_photo_path'] ?? ''),
                'clock_out_photo_path' => (string) ($row['clock_out_photo_path'] ?? ''),
                'hours_rendered' => sprintf('%dh %dm', (int) floor($secs / 3600), (int) floor(($secs % 3600) / 60)),
                'classification' => $classification,
                'overtime_minutes' => (int) floor($otSeconds / 60),
                'overtime_status' => (string) ($row['overtime_status'] ?? 'none'),
                'is_edited' => (int) ($row['is_edited'] ?? 0) === 1,
                'edit_reason' => (string) ($row['edit_reason'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
            ];
        }

        return view_page('Attendance', 'tenant.laundry.attendance', [
            'rows' => $rows,
            'range_from' => $from,
            'range_to' => $to,
            'premium_trial_browse_lock' => $attendanceLocked,
            'attendance_locked' => $attendanceLocked,
        ]);
    }

    public function attendanceUpdate(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $userId = (int) (Auth::user()['id'] ?? 0);
        $timeIn = trim((string) $request->input('clock_in_at', ''));
        $timeOut = trim((string) $request->input('clock_out_at', ''));
        $reason = trim((string) $request->input('edit_reason', ''));
        if ($reason === '') {
            session_flash('errors', ['Edit reason is required.']);

            return redirect(route('tenant.attendance.index'));
        }
        $inTs = strtotime($timeIn);
        $outTs = $timeOut !== '' ? strtotime($timeOut) : false;
        if ($inTs === false || ($timeOut !== '' && $outTs === false) || ($outTs !== false && $outTs <= $inTs)) {
            session_flash('errors', ['Enter valid time in/time out values.']);

            return redirect(route('tenant.attendance.index'));
        }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT clock_in_at, clock_out_at FROM laundry_time_logs WHERE tenant_id = ? AND id = ? FOR UPDATE');
            $st->execute([$tenantId, $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                throw new \RuntimeException('Attendance record not found.');
            }
            $hasUserWorkingHours = $this->hasColumn($pdo, 'users', 'working_hours_per_day');
            $hasBranchPayrollHours = $this->hasColumn($pdo, 'laundry_branch_configs', 'payroll_hours_per_day');
            $hasBranchOtIncentive = $this->hasColumn($pdo, 'laundry_branch_configs', 'activate_ot_incentives');
            $hoursExpr = $hasUserWorkingHours
                ? ($hasBranchPayrollHours ? 'COALESCE(u.working_hours_per_day, bc.payroll_hours_per_day, 8.00)' : 'COALESCE(u.working_hours_per_day, 8.00)')
                : ($hasBranchPayrollHours ? 'COALESCE(bc.payroll_hours_per_day, 8.00)' : '8.00');
            $otExpr = $hasBranchOtIncentive ? 'COALESCE(bc.activate_ot_incentives, 0)' : '0';
            $hoursRow = $pdo->prepare(
                'SELECT '.$hoursExpr.' AS hours_per_day,
                        '.$otExpr.' AS activate_ot_incentives
                 FROM laundry_time_logs tl
                 INNER JOIN users u ON u.id = tl.user_id
                 LEFT JOIN laundry_branch_configs bc ON bc.tenant_id = tl.tenant_id
                 WHERE tl.tenant_id = ? AND tl.id = ?
                 LIMIT 1'
            );
            $hoursRow->execute([$tenantId, $id]);
            $hoursCfg = $hoursRow->fetch(PDO::FETCH_ASSOC) ?: [];
            $requiredSeconds = (int) round(max(1.0, min(24.0, (float) ($hoursCfg['hours_per_day'] ?? 8))) * 3600);
            $renderedSeconds = $outTs !== false ? max(0, $outTs - $inTs) : 0;
            $otStatus = ((int) ($hoursCfg['activate_ot_incentives'] ?? 0) === 1 && ($renderedSeconds - $requiredSeconds) >= 1800) ? 'pending' : 'none';
            $pdo->prepare(
                'UPDATE laundry_time_logs
                 SET original_clock_in_at = COALESCE(original_clock_in_at, clock_in_at),
                     original_clock_out_at = COALESCE(original_clock_out_at, clock_out_at),
                     clock_in_at = ?,
                     clock_out_at = ?,
                     is_edited = 1,
                     edited_by = ?,
                     edited_at = NOW(),
                     edit_reason = ?,
                     overtime_status = IF(overtime_status = "approved" AND ? = "pending", "approved", ?),
                     updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            )->execute([
                date('Y-m-d H:i:s', $inTs),
                $outTs !== false ? date('Y-m-d H:i:s', $outTs) : null,
                $userId > 0 ? $userId : null,
                $reason,
                $otStatus,
                $otStatus,
                $tenantId,
                $id,
            ]);
            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.attendance.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        session_flash('success', 'Attendance updated.');

        return redirect(route('tenant.attendance.index'));
    }

    public function attendanceApproveOt(Request $request, int|string $id): Response
    {
        $id = (int) $id;
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $userId = (int) (Auth::user()['id'] ?? 0);
        $pdo->prepare(
            'UPDATE laundry_time_logs
             SET overtime_status = "approved", overtime_approved_by = ?, overtime_approved_at = NOW(), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND overtime_status = "pending"'
        )->execute([$userId > 0 ? $userId : null, $tenantId, $id]);
        session_flash('success', 'Overtime approved.');

        return redirect(route('tenant.attendance.index'));
    }

    public function payrollIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $shopName = trim((string) ($this->scalar(
            $pdo,
            'SELECT name FROM tenants WHERE id = ? LIMIT 1',
            [$tenantId]
        ) ?? ''));
        if ($shopName === '') {
            $shopName = 'Laundry Shop';
        }
        $cutoffDays = $this->getBranchPayrollCutoffDays($pdo, $tenantId);
        $hoursPerDay = $this->getBranchPayrollHoursPerDay($pdo, $tenantId);
        $activateCommission = $this->getBranchBoolConfig($pdo, $tenantId, 'activate_commission', false);
        $activateOtIncentives = $this->getBranchBoolConfig($pdo, $tenantId, 'activate_ot_incentives', false);
        $dailyLoadQuota = $this->getBranchIntConfig($pdo, $tenantId, 'daily_load_quota', 0);
        $commissionRatePerLoad = $this->getBranchFloatConfig($pdo, $tenantId, 'commission_rate_per_load', 0.0);
        $today = date('Y-m-d');
        $anchor = trim((string) $request->query('date', $today));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor) !== 1) {
            $anchor = $today;
        }
        $anchorTs = strtotime($anchor.' 00:00:00') ?: time();
        $cutoffEnd = date('Y-m-d', $anchorTs);
        $cutoffStart = date('Y-m-d', strtotime('-'.max(0, $cutoffDays - 1).' days', $anchorTs) ?: $anchorTs);
        $payrollLocked = Auth::isTenantFreePlanRestricted(Auth::user());

        if ($payrollLocked) {
            return view_page('Payroll', 'tenant.laundry.payroll', [
                'rows' => [],
                'cutoff_start' => $cutoffStart,
                'cutoff_end' => $cutoffEnd,
                'cutoff_days' => $cutoffDays,
                'hours_per_day' => $hoursPerDay,
                'overall_total_salary' => 0.0,
                'fold_service_amount' => 0.0,
                'fold_commission_target' => 'per_order_type',
                'activate_commission' => false,
                'activate_ot_incentives' => false,
                'shop_name' => $shopName,
                'premium_trial_browse_lock' => true,
                'payroll_locked' => true,
            ]);
        }

        $hasDayRate = self::hasUsersDayRateColumn($pdo);
        $hasOvertimeRate = self::hasUsersOvertimeRateColumn($pdo);
        $hasWorkDays = self::hasUsersWorkDaysCsvColumn($pdo);
        $hasWorkingHours = $this->hasColumn($pdo, 'users', 'working_hours_per_day');
        $hasCommissionEligible = $this->hasColumn($pdo, 'users', 'commission_eligible');
        $hasOrdersIncludeFoldService = $this->hasColumn($pdo, 'laundry_orders', 'include_fold_service');
        $hasOrdersOrderMode = $this->hasColumn($pdo, 'laundry_orders', 'order_mode');
        $hasOrdersFoldServiceQty = $this->hasColumn($pdo, 'laundry_orders', 'fold_service_qty');
        $hasOrdersNumberOfLoads = $this->hasColumn($pdo, 'laundry_orders', 'number_of_loads');
        $hasOrdersCreatedBy = $this->hasColumn($pdo, 'laundry_orders', 'created_by_user_id');
        $hasOrderTypesFoldTarget = $this->hasColumn($pdo, 'laundry_order_types', 'fold_commission_target');
        $hasOrderTypesFoldAmount = $this->hasColumn($pdo, 'laundry_order_types', 'fold_service_amount');
        $hasOrderTypesFoldStaffShare = $this->hasColumn($pdo, 'laundry_order_types', 'fold_staff_share_amount');
        $includeFoldExpr = $hasOrdersIncludeFoldService ? 'o.include_fold_service' : '0';
        $orderModeExpr = $hasOrdersOrderMode ? 'o.order_mode' : '"drop_off"';
        $foldQtyExpr = $hasOrdersFoldServiceQty ? 'o.fold_service_qty' : '0';
        $loadCountExpr = $hasOrdersNumberOfLoads ? 'o.number_of_loads' : '1';
        $foldTargetExpr = $hasOrderTypesFoldTarget ? 'fold_cfg.fold_commission_target' : '"branch"';
        $foldServiceAmountExpr = $hasOrderTypesFoldAmount ? 'fold_cfg.fold_service_amount' : '0';
        $foldStaffShareExpr = $hasOrderTypesFoldStaffShare ? 'fold_cfg.fold_staff_share_amount' : $foldServiceAmountExpr;
        $staffSql = sprintf(
            'SELECT id, name, role, %s AS day_rate, %s AS overtime_rate_per_hour, %s AS work_days_csv, %s AS working_hours_per_day, %s AS commission_eligible
             FROM users
             WHERE tenant_id = ? AND role = "cashier"
             ORDER BY name ASC',
            $hasDayRate ? 'COALESCE(day_rate, 350)' : '350',
            $hasOvertimeRate ? 'COALESCE(overtime_rate_per_hour, 0)' : '0',
            $hasWorkDays ? 'COALESCE(work_days_csv, "1,2,3,4,5,6,7")' : '"1,2,3,4,5,6,7"',
            $hasWorkingHours ? 'COALESCE(working_hours_per_day, 8.00)' : '8.00',
            $hasCommissionEligible ? 'COALESCE(commission_eligible, 0)' : '0'
        );
        $staffSt = $pdo->prepare($staffSql);
        $staffSt->execute([$tenantId]);
        $staffList = $staffSt->fetchAll(PDO::FETCH_ASSOC);

        $eligibleStaffCount = 0;
        foreach ($staffList as $staff) {
            if ((int) ($staff['commission_eligible'] ?? 0) === 1) {
                $eligibleStaffCount++;
            }
        }
        $branchCommissionPool = $activateCommission
            ? $this->computeBranchCommissionPool($pdo, $tenantId, $cutoffStart, $cutoffEnd, $dailyLoadQuota, $commissionRatePerLoad)
            : 0.0;
        $commissionShare = ($activateCommission && $eligibleStaffCount > 0) ? ($branchCommissionPool / $eligibleStaffCount) : 0.0;
        $rows = [];
        $overallTotal = 0.0;
        foreach ($staffList as $staff) {
            $uid = (int) ($staff['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $workDays = $this->parseWorkDaysCsv((string) ($staff['work_days_csv'] ?? '1,2,3,4,5,6,7'));
            $scheduledDays = $this->countScheduledDaysInRange($cutoffStart, $cutoffEnd, $workDays);
            $staffHoursPerDay = max(1.0, min(24.0, (float) ($staff['working_hours_per_day'] ?? $hoursPerDay)));
            $dayRate = (float) ($staff['day_rate'] ?? 350);
            $otRate = (float) ($staff['overtime_rate_per_hour'] ?? 0);
            $attendanceTotals = $this->computePayrollAttendanceTotals($pdo, $tenantId, $uid, $cutoffStart, $cutoffEnd, $staffHoursPerDay, $activateOtIncentives);
            $renderedSeconds = (int) ($attendanceTotals['rendered_seconds'] ?? 0);
            $requiredSeconds = (int) round($scheduledDays * $staffHoursPerDay * 3600);
            $overtimeHoursCredit = (float) ($attendanceTotals['approved_ot_hours'] ?? 0.0);
            $basePay = (float) ($attendanceTotals['pay_units'] ?? 0.0) * $dayRate;
            $overtimePay = $overtimeHoursCredit * $otRate;
            if (! $hasOrdersCreatedBy) {
                $loadsFolded = 0;
                $foldingFeeTotal = 0.0;
            } else {
            $foldAggSt = $pdo->prepare(
                'SELECT
                    COALESCE(SUM(CASE
                        WHEN '.$includeFoldExpr.' = 1
                            AND '.$orderModeExpr.' <> "self_service"
                            THEN GREATEST(1, COALESCE('.$loadCountExpr.', 0))
                        WHEN '.$orderModeExpr.' = "self_service"
                            THEN GREATEST(0, COALESCE('.$foldQtyExpr.', 0))
                        ELSE 0
                    END), 0) AS loads_folded,
                    COALESCE(SUM(CASE
                        WHEN LOWER(COALESCE('.$foldTargetExpr.', "branch")) = "staff"
                            THEN (
                                CASE
                                    WHEN '.$includeFoldExpr.' = 1
                                        AND '.$orderModeExpr.' <> "self_service"
                                        THEN GREATEST(1, COALESCE('.$loadCountExpr.', 0))
                                    WHEN '.$orderModeExpr.' = "self_service"
                                        THEN GREATEST(0, COALESCE('.$foldQtyExpr.', 0))
                                    ELSE 0
                                END
                            ) * GREATEST(0, COALESCE('.$foldStaffShareExpr.', '.$foldServiceAmountExpr.', 0))
                        ELSE 0
                    END), 0) AS folding_fee_total
                 FROM laundry_orders o
                 LEFT JOIN laundry_order_types fold_cfg ON fold_cfg.tenant_id = o.tenant_id AND fold_cfg.code = "fold_with_price"
                 WHERE o.tenant_id = ? AND o.created_by_user_id = ?
                   AND o.created_at BETWEEN ? AND ?
                   AND COALESCE(o.is_void, 0) = 0
                   AND o.status <> "void"
                   AND COALESCE(o.is_free, 0) = 0
                   AND COALESCE(o.is_reward, 0) = 0
                   AND o.status = "paid"'
            );
            $foldAggSt->execute([$tenantId, $uid, $cutoffStart.' 00:00:00', $cutoffEnd.' 23:59:59']);
            $foldAggregation = $foldAggSt->fetch(PDO::FETCH_ASSOC) ?: [];
            $loadsFolded = (int) round((float) ($foldAggregation['loads_folded'] ?? 0));
            $foldingFeeTotal = (float) ($foldAggregation['folding_fee_total'] ?? 0.0);
            }
            $quotaCommission = ($activateCommission && (int) ($staff['commission_eligible'] ?? 0) === 1) ? $commissionShare : 0.0;
            $totalSalary = $basePay + $overtimePay + $foldingFeeTotal + $quotaCommission;
            $overallTotal += $totalSalary;
            $rows[] = [
                'staff_name' => (string) ($staff['name'] ?? ''),
                'day_rate' => $dayRate,
                'overtime_rate_per_hour' => $otRate,
                'working_hours_per_day' => $staffHoursPerDay,
                'commission_eligible' => (int) ($staff['commission_eligible'] ?? 0) === 1,
                'scheduled_days' => $scheduledDays,
                'required_hours' => round($requiredSeconds / 3600, 2),
                'rendered_hours' => round($renderedSeconds / 3600, 2),
                'pay_units' => (float) ($attendanceTotals['pay_units'] ?? 0.0),
                'overtime_hours_credit' => $overtimeHoursCredit,
                'overtime_pay' => $overtimePay,
                'loads_folded' => $loadsFolded,
                'folding_fee_total' => $foldingFeeTotal,
                'quota_commission' => $quotaCommission,
                'base_pay' => $basePay,
                'total_salary' => $totalSalary,
                'work_days_text' => $this->workDaysLabel($workDays),
            ];
        }

        return view_page('Payroll', 'tenant.laundry.payroll', [
            'rows' => $rows,
            'cutoff_start' => $cutoffStart,
            'cutoff_end' => $cutoffEnd,
            'cutoff_days' => $cutoffDays,
            'hours_per_day' => $hoursPerDay,
            'overall_total_salary' => $overallTotal,
            'fold_service_amount' => 0.0,
            'fold_commission_target' => 'per_order_type',
            'activate_commission' => $activateCommission,
            'activate_ot_incentives' => $activateOtIncentives,
            'daily_load_quota' => $dailyLoadQuota,
            'commission_rate_per_load' => $commissionRatePerLoad,
            'branch_commission_pool' => $branchCommissionPool,
            'eligible_staff_count' => $eligibleStaffCount,
            'shop_name' => $shopName,
            'premium_trial_browse_lock' => Auth::isTenantFreePlanRestricted(Auth::user()),
        ]);
    }

    public function payrollStore(Request $request): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: payroll actions are not available in Free Mode.']);

            return redirect(route('tenant.payroll.index'));
        }
        session_flash('status', 'Payroll is now auto-calculated from time logs and transaction logs.');

        return redirect(route('tenant.payroll.index'));
    }

    public function redeemConfigIndex(Request $request): Response
    {
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $cfgSt = $pdo->prepare(
            'SELECT *
             FROM laundry_reward_configs
             WHERE tenant_id = ?
             LIMIT 1'
        );
        $cfgSt->execute([$tenantId]);
        $config = $cfgSt->fetch(PDO::FETCH_ASSOC) ?: [
            'points_per_amount_spent' => 0,
            'points_per_dropoff_load' => 1,
            'reward_name' => 'Reward',
            'reward_description' => '',
            'reward_points_cost' => 10,
            'reward_order_type_code' => null,
            'reward_quantity' => 1,
            'minimum_points_to_redeem' => 10,
            'is_active' => 1,
        ];

        $customerSt = $pdo->prepare(
            'SELECT c.id, c.name, COALESCE(cp.points_balance, 0) AS rewards_balance
             FROM laundry_customers c
             LEFT JOIN laundry_customer_points cp ON cp.customer_id = c.id AND cp.tenant_id = c.tenant_id
             WHERE c.tenant_id = ?
             ORDER BY c.name ASC'
        );
        $customerSt->execute([$tenantId]);

        $redeemSt = $pdo->prepare(
            'SELECT rr.*, c.name AS customer_name
             FROM laundry_reward_redemptions rr
             INNER JOIN laundry_customers c ON c.id = rr.customer_id
             WHERE rr.tenant_id = ?
             ORDER BY rr.id DESC
             LIMIT 100'
        );
        $redeemSt->execute([$tenantId]);

        return view_page('Rewards', 'tenant.laundry.redeem-config', [
            'reward_config' => $config,
            'order_types' => $this->fetchActiveOrderTypes($pdo, $tenantId),
            'customer_points' => $customerSt->fetchAll(PDO::FETCH_ASSOC),
            'redemptions' => $redeemSt->fetchAll(PDO::FETCH_ASSOC),
            'premium_trial_browse_lock' => Auth::isTenantFreePlanRestricted(Auth::user()),
        ]);
    }

    public function redeemConfigUpdate(Request $request): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: rewards actions are not available in Free Mode.']);

            return redirect(route('tenant.redeem-config.index'));
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];

        $prevCfgSt = $pdo->prepare('SELECT is_active FROM laundry_reward_configs WHERE tenant_id = ? LIMIT 1');
        $prevCfgSt->execute([$tenantId]);
        $prevCfgRow = $prevCfgSt->fetch(PDO::FETCH_ASSOC);
        $wasRewardSystemActive = is_array($prevCfgRow) && (int) ($prevCfgRow['is_active'] ?? 0) === 1;

        $fullServicesRequired = max(1, (int) $request->input('full_services_required', 10));
        $rewardName = trim((string) $request->input('reward_name', 'Reward'));
        $rewardDescription = trim((string) $request->input('reward_description', ''));
        $rewardOrderTypeCode = trim((string) $request->input('reward_order_type_code', ''));
        $rewardQuantity = max(1, (int) $request->input('reward_quantity', 1));
        $rewardPointsCost = $fullServicesRequired;
        $minimumPointsToRedeem = $fullServicesRequired;
        $isActive = $request->boolean('is_active') ? 1 : 0;

        if ($rewardName === '') {
            $rewardName = 'Reward';
        }
        if ($rewardOrderTypeCode !== '' && $this->fetchOrderTypeByCode($pdo, $tenantId, $rewardOrderTypeCode) === null) {
            session_flash('errors', ['Select a valid reward service.']);

            return redirect(route('tenant.redeem-config.index'));
        }

        $pdo->prepare(
            'INSERT INTO laundry_reward_configs
             (tenant_id, points_per_amount_spent, points_per_dropoff_load, reward_name, reward_description, reward_points_cost, reward_order_type_code, reward_quantity, minimum_points_to_redeem, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                points_per_amount_spent = VALUES(points_per_amount_spent),
                points_per_dropoff_load = VALUES(points_per_dropoff_load),
                reward_name = VALUES(reward_name),
                reward_description = VALUES(reward_description),
                reward_points_cost = VALUES(reward_points_cost),
                reward_order_type_code = VALUES(reward_order_type_code),
                reward_quantity = VALUES(reward_quantity),
                minimum_points_to_redeem = VALUES(minimum_points_to_redeem),
                is_active = VALUES(is_active),
                updated_at = NOW()'
        )->execute([
            $tenantId,
            0,
            1,
            $rewardName,
            $rewardDescription !== '' ? $rewardDescription : null,
            $rewardPointsCost,
            $rewardOrderTypeCode !== '' ? $rewardOrderTypeCode : null,
            $rewardQuantity,
            $minimumPointsToRedeem,
            $isActive,
        ]);

        if ($isActive === 1 && ! $wasRewardSystemActive) {
            try {
                $pdo->prepare(
                    'UPDATE laundry_order_types SET include_in_rewards = 1 WHERE tenant_id = ? AND service_kind = ?'
                )->execute([$tenantId, 'full_service']);
            } catch (\Throwable) {
            }
        }

        session_flash('success', 'Rewards config updated.');

        return redirect(route('tenant.redeem-config.index'));
    }

    public function redeemGift(Request $request): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            session_flash('errors', ['Premium: rewards actions are not available in Free Mode.']);

            return redirect(route('tenant.redeem-config.index'));
        }
        $ctx = $this->baseContext();
        $pdo = $ctx['pdo'];
        $tenantId = $ctx['tenant_id'];
        $userId = (int) (Auth::user()['id'] ?? 0);
        $customerId = (int) $request->input('customer_id', 0);

        if ($customerId < 1) {
            session_flash('errors', ['Please select a customer for redemption.']);

            return redirect(route('tenant.redeem-config.index'));
        }

        $cfgSt = $pdo->prepare('SELECT * FROM laundry_reward_configs WHERE tenant_id = ? LIMIT 1');
        $cfgSt->execute([$tenantId]);
        $cfg = $cfgSt->fetch(PDO::FETCH_ASSOC);
        if (! $cfg || ! (bool) ($cfg['is_active'] ?? false)) {
            session_flash('errors', ['Rewards config is inactive.']);

            return redirect(route('tenant.redeem-config.index'));
        }

        $rewardName = trim((string) ($cfg['reward_name'] ?? 'Reward'));
        $cost = max(1.0, (float) ($cfg['reward_points_cost'] ?? 10));
        $min = max(1.0, (float) ($cfg['minimum_points_to_redeem'] ?? $cost));

        $pdo->beginTransaction();
        try {
            $ptSt = $pdo->prepare(
                'SELECT id, points_balance, lifetime_redeemed
                 FROM laundry_customer_points
                 WHERE tenant_id = ? AND customer_id = ?
                 FOR UPDATE'
            );
            $ptSt->execute([$tenantId, $customerId]);
            $pt = $ptSt->fetch(PDO::FETCH_ASSOC);
            if (! $pt) {
                throw new \RuntimeException('Customer has no reward count yet.');
            }
            $balance = (float) ($pt['points_balance'] ?? 0);
            if ($balance < $min || $balance < $cost) {
                throw new \RuntimeException('Customer does not have enough reward count to redeem.');
            }

            $pdo->prepare(
                'UPDATE laundry_customer_points
                 SET points_balance = points_balance - ?, lifetime_redeemed = lifetime_redeemed + ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([$cost, $cost, (int) ($pt['id'] ?? 0)]);

            $pdo->prepare(
                'INSERT INTO laundry_reward_redemptions (tenant_id, customer_id, reward_name, points_used, redeemed_by_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([$tenantId, $customerId, $rewardName, $cost, $userId > 0 ? $userId : null]);

            $pdo->commit();
        } catch (\RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(route('tenant.redeem-config.index'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        session_flash('success', 'Reward redeemed successfully.');

        return redirect(route('tenant.redeem-config.index'));
    }

    /**
     * @return array{pdo:PDO,tenant_id:int}
     */
    private function baseContext(): array
    {
        $user = Auth::user();
        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        LaundrySchema::ensureMachineCreditColumns($pdo);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        $activeTenantId = (int) session_get('active_tenant_id', 0);
        // Only tenant_admin can switch active branch context.
        // Cashier/staff should always use their assigned tenant_id to avoid
        // reading another branch's workflow toggle by mistake.
        if ($role === 'tenant_admin' && $activeTenantId > 0) {
            $tenantId = $activeTenantId;
        }

        return [
            'pdo' => $pdo,
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchActiveOrderTypes(PDO $pdo, int $tenantId): array
    {
        try {
            $hasFoldAmountColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_service_amount');
            $hasFoldTargetColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_commission_target');
            $hasFoldStaffShareColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fold_staff_share_amount');
            $hasShowInModeColumn = $this->hasColumn($pdo, 'laundry_order_types', 'show_in_order_mode');
            $hasDetergentQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'detergent_qty');
            $hasFabconQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'fabcon_qty');
            $hasBleachQtyColumn = $this->hasColumn($pdo, 'laundry_order_types', 'bleach_qty');
            $foldAmountSelect = $hasFoldAmountColumn ? 'fold_service_amount' : '10 AS fold_service_amount';
            $foldTargetSelect = $hasFoldTargetColumn ? 'fold_commission_target' : '\'branch\' AS fold_commission_target';
            $foldStaffShareSelect = $hasFoldStaffShareColumn ? 'fold_staff_share_amount' : '10 AS fold_staff_share_amount';
            $showInModeSelect = $hasShowInModeColumn ? 'show_in_order_mode' : '\'both\' AS show_in_order_mode';
            $detergentQtySelect = $hasDetergentQtyColumn
                ? 'detergent_qty'
                : 'CASE WHEN supply_block = "full_service_2x" THEN 2 WHEN supply_block IN ("full_service","wash_supplies") THEN 1 ELSE 0 END AS detergent_qty';
            $fabconQtySelect = $hasFabconQtyColumn
                ? 'fabcon_qty'
                : 'CASE WHEN supply_block = "full_service_2x" THEN 2 WHEN supply_block IN ("full_service","wash_supplies","rinse_supplies") THEN 1 ELSE 0 END AS fabcon_qty';
            $bleachQtySelect = $hasBleachQtyColumn
                ? 'bleach_qty'
                : '0 AS bleach_qty';
            $st = $pdo->prepare(
                'SELECT id, code, label, service_kind, '.$showInModeSelect.', supply_block, show_addon_supplies, required_weight, '.$detergentQtySelect.', '.$fabconQtySelect.', '.$bleachQtySelect.', '.$foldAmountSelect.', '.$foldTargetSelect.', '.$foldStaffShareSelect.', price_per_load, max_weight_kg, excess_weight_fee_per_kg, sort_order, include_in_rewards
                 FROM laundry_order_types
                 WHERE tenant_id = ? AND is_active = 1
                 ORDER BY id ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function isMachineAssignmentEnabled(PDO $pdo, int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        try {
            $st = $pdo->prepare(
                'SELECT machine_assignment_enabled
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return false;
            }

            return (int) ($row['machine_assignment_enabled'] ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function persistMachineAssignmentConfig(PDO $pdo, int $tenantId, bool $enabled): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'machine_assignment_enabled')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, machine_assignment_enabled, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE machine_assignment_enabled = VALUES(machine_assignment_enabled), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    private function persistLaundryStatusTrackingConfig(PDO $pdo, int $tenantId, bool $enabled): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'laundry_status_tracking_enabled')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, laundry_status_tracking_enabled, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE laundry_status_tracking_enabled = VALUES(laundry_status_tracking_enabled), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    private function isLaundryStatusTrackingEnabled(PDO $pdo, int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        try {
            $st = $pdo->prepare(
                'SELECT laundry_status_tracking_enabled
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return false;
            }

            return (int) ($row['laundry_status_tracking_enabled'] ?? 0) === 1;
        } catch (\Throwable) {
            // Fail-safe: prefer simplified workflow when config read fails,
            // instead of forcing full workflow unexpectedly.
            return false;
        }
    }

    private function persistEditableOrderDateConfig(PDO $pdo, int $tenantId, bool $enabled): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'editable_order_date')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, editable_order_date, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE editable_order_date = VALUES(editable_order_date), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    private function isEditableOrderDateEnabled(PDO $pdo, int $tenantId): bool
    {
        return $this->getBranchBoolConfig($pdo, $tenantId, 'editable_order_date', false);
    }

    private function isTrackMachineMovementEnabled(PDO $pdo, int $tenantId): bool
    {
        return $this->getBranchBoolConfig($pdo, $tenantId, 'track_machine_movement', false);
    }

    private function persistTrackMachineMovementConfig(PDO $pdo, int $tenantId, bool $enabled): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'track_machine_movement')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, track_machine_movement, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE track_machine_movement = VALUES(track_machine_movement), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    private function getBranchDefaultDryingMinutes(PDO $pdo, int $tenantId): ?int
    {
        $value = $this->getBranchScalarConfig($pdo, $tenantId, 'default_drying_minutes', null);
        if ($value === null || $value === '') {
            return null;
        }
        return max(1, (int) $value);
    }

    private function persistBranchDefaultDryingMinutes(PDO $pdo, int $tenantId, ?int $minutes): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'default_drying_minutes')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, default_drying_minutes, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE default_drying_minutes = VALUES(default_drying_minutes), updated_at = NOW()'
        )->execute([$tenantId, $minutes]);
    }

    private function processTrackMachineMovementTimers(PDO $pdo, int $tenantId): void
    {
        if ($tenantId < 1 || ! $this->isTrackMachineMovementEnabled($pdo, $tenantId)) {
            return;
        }
        $defaultDryingMinutes = $this->getBranchDefaultDryingMinutes($pdo, $tenantId);
        try {
            $pdo->beginTransaction();
            $washToDry = $pdo->prepare(
                'SELECT id, payment_status, wash_qty
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND status = "washing_drying"
                   AND (
                       (track_machine_stage = "washing_rinsing" AND wash_rinse_end_at IS NOT NULL AND wash_rinse_end_at <= NOW())
                       OR track_machine_stage = "drying_waiting_machine"
                   )
                   AND COALESCE(is_void, 0) = 0
                 ORDER BY wash_rinse_end_at ASC
                 FOR UPDATE'
            );
            $washToDry->execute([$tenantId]);
            $rows = $washToDry->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $orderId = (int) ($row['id'] ?? 0);
                if ($orderId < 1) {
                    continue;
                }
                $pdo->prepare('UPDATE laundry_machines SET status = "available", current_order_id = NULL, updated_at = NOW() WHERE tenant_id = ? AND current_order_id = ? AND machine_kind = "washer"')
                    ->execute([$tenantId, $orderId]);
                $dryer = $this->fetchFirstAvailableMachineByKind($pdo, $tenantId, 'dryer');
                if (! is_array($dryer) || (int) ($dryer['id'] ?? 0) < 1) {
                    $pdo->prepare(
                        'UPDATE laundry_orders
                         SET track_machine_stage = "drying_waiting_machine",
                             movement_last_error = "Ready for drying but no machine available",
                             updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute([$tenantId, $orderId]);
                    continue;
                }
                $markDryerRunning = $pdo->prepare(
                    'UPDATE laundry_machines
                     SET status = "running", current_order_id = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND id = ? AND status = "available"'
                );
                $markDryerRunning->execute([$orderId, $tenantId, (int) $dryer['id']]);
                if ($markDryerRunning->rowCount() < 1) {
                    $pdo->prepare(
                        'UPDATE laundry_orders
                         SET track_machine_stage = "drying_waiting_machine",
                             movement_last_error = "All machines are currently in use",
                             updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?'
                    )->execute([$tenantId, $orderId]);
                    continue;
                }
                $usageQty = max(1, (int) ($row['wash_qty'] ?? 1));
                $this->deductMachineCredits($pdo, $tenantId, [$dryer], $usageQty, $orderId);
                $dryingMinutes = $defaultDryingMinutes;
                $pdo->prepare(
                    'UPDATE laundry_orders
                     SET track_machine_stage = "drying",
                         movement_last_error = NULL,
                         drying_minutes = ?,
                         drying_machine_id = ?,
                         drying_started_at = NOW(),
                         drying_end_at = IF(? IS NULL, NULL, DATE_ADD(NOW(), INTERVAL ? MINUTE)),
                         machine_id = ?,
                         dryer_machine_id = ?,
                         machine_type = "dryer",
                         updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute([
                    $dryingMinutes,
                    (int) $dryer['id'],
                    $dryingMinutes,
                    $dryingMinutes ?? 0,
                    (int) $dryer['id'],
                    (int) $dryer['id'],
                    $tenantId,
                    $orderId,
                ]);
            }

            $dryDone = $pdo->prepare(
                'SELECT id, payment_status
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND status = "washing_drying"
                   AND track_machine_stage = "drying"
                   AND drying_end_at IS NOT NULL
                   AND drying_end_at <= NOW()
                   AND COALESCE(is_void, 0) = 0
                 ORDER BY drying_end_at ASC
                 FOR UPDATE'
            );
            $dryDone->execute([$tenantId]);
            $doneRows = $dryDone->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($doneRows as $row) {
                $orderId = (int) ($row['id'] ?? 0);
                if ($orderId < 1) {
                    continue;
                }
                $pdo->prepare('UPDATE laundry_machines SET status = "available", current_order_id = NULL, updated_at = NOW() WHERE tenant_id = ? AND current_order_id = ?')
                    ->execute([$tenantId, $orderId]);
                $pdo->prepare(
                    'UPDATE laundry_orders
                     SET status = "washing_drying",
                         track_machine_stage = "drying_done",
                         movement_completed_at = NOW(),
                         movement_last_error = NULL,
                         machine_id = NULL,
                         washer_machine_id = NULL,
                         dryer_machine_id = NULL,
                         updated_at = NOW()
                     WHERE tenant_id = ? AND id = ?'
                )->execute([$tenantId, $orderId]);
            }
            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    private function isBranchTrackGasulUsageEnabled(PDO $pdo, int $tenantId): bool
    {
        return $this->getBranchBoolConfig($pdo, $tenantId, 'track_gasul_usage', false);
    }

    private function isBranchBluetoothPrintEnabled(PDO $pdo, int $tenantId): bool
    {
        return $this->getBranchBoolConfig($pdo, $tenantId, 'enable_bluetooth_print', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOrderTypeByCode(PDO $pdo, int $tenantId, string $code): ?array
    {
        try {
            if ($code === '') {
                return null;
            }
            $st = $pdo->prepare(
                'SELECT * FROM laundry_order_types WHERE tenant_id = ? AND code = ? LIMIT 1'
            );
            $st->execute([$tenantId, $code]);

            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchFirstAvailableMachineByKind(PDO $pdo, int $tenantId, string $kind): ?array
    {
        try {
            $st = $pdo->prepare(
                'SELECT id, machine_label, machine_kind, machine_type, credit_required, credit_balance
                 FROM laundry_machines
                 WHERE tenant_id = ?
                   AND machine_kind = ?
                   AND status = "available"
                 ORDER BY machine_label ASC, id ASC
                 LIMIT 1'
            );
            $st->execute([$tenantId, $kind]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return null;
            }
            if ((int) ($row['credit_required'] ?? 0) === 1 && (float) ($row['credit_balance'] ?? 0) <= 0) {
                return null;
            }
            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    private function computeBasePriceFromKind(string $serviceKind, float $pricePerLoad, int $washQty, int $dryQty): float
    {
        if ($serviceKind === 'fold_only') {
            return max(1, max($washQty, $dryQty)) * $pricePerLoad;
        }
        if ($serviceKind === 'dry_only') {
            return max(1, $dryQty) * $pricePerLoad;
        }
        if ($serviceKind === 'wash_only' || $serviceKind === 'rinse_only') {
            return max(1, $washQty) * $pricePerLoad;
        }

        return max(1, max($washQty, $dryQty)) * $pricePerLoad;
    }

    private function slugifyOrderTypeLabel(string $label): string
    {
        $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($label)));
        $s = trim($s, '_');
        if ($s === '') {
            return 'order_type';
        }

        return substr($s, 0, 64);
    }

    private function orderTypeCodeExists(PDO $pdo, int $tenantId, string $code): bool
    {
        $st = $pdo->prepare('SELECT 1 FROM laundry_order_types WHERE tenant_id = ? AND code = ? LIMIT 1');
        $st->execute([$tenantId, $code]);

        return $st->fetch() !== false;
    }

    private function generateUniqueOrderTypeCode(PDO $pdo, int $tenantId, string $label): string
    {
        $base = $this->slugifyOrderTypeLabel($label);
        $code = $base;
        $n = 2;
        while ($this->orderTypeCodeExists($pdo, $tenantId, $code)) {
            $suffix = '_'.$n;
            $maxBase = max(1, 64 - strlen($suffix));
            $code = substr($base, 0, $maxBase).$suffix;
            $n++;
        }

        return $code;
    }

    private function defaultSupplyBlockForServiceKind(string $serviceKind): string
    {
        return match ($serviceKind) {
            'full_service' => 'full_service',
            'wash_only' => 'wash_supplies',
            'rinse_only' => 'rinse_supplies',
            default => 'none',
        };
    }

    private function resolveOrderTypeSupplyQty(?array $orderTypeDef, string $column, string $serviceKind, string $supplyBlock): float
    {
        $raw = is_array($orderTypeDef) ? ($orderTypeDef[$column] ?? null) : null;
        if ($raw !== null && $raw !== '') {
            return max(0.0, (float) $raw);
        }

        return $this->legacySupplyQtyByBlock($column, $serviceKind, $supplyBlock);
    }

    private function legacySupplyQtyByBlock(string $column, string $serviceKind, string $supplyBlock): float
    {
        if ($column === 'detergent_qty') {
            if ($serviceKind === 'full_service' && $supplyBlock === 'full_service_2x') {
                return 2.0;
            }
            if (in_array($supplyBlock, ['full_service', 'wash_supplies'], true) && $serviceKind === 'full_service') {
                return 1.0;
            }

            return 0.0;
        }
        if ($column === 'fabcon_qty') {
            if ($serviceKind === 'full_service' && $supplyBlock === 'full_service_2x') {
                return 2.0;
            }
            if (in_array($supplyBlock, ['full_service', 'wash_supplies'], true) && $serviceKind === 'full_service') {
                return 1.0;
            }
            if ($supplyBlock === 'rinse_supplies') {
                return 1.0;
            }

            return 0.0;
        }
        if ($column === 'bleach_qty') {
            // Legacy flow treated bleach as optional; keep default at zero unless explicitly configured.
            return 0.0;
        }

        return 0.0;
    }

    private function defaultShowAddonSuppliesForServiceKind(string $serviceKind): bool
    {
        return in_array($serviceKind, ['full_service', 'wash_only'], true);
    }

    private function normalizeOrderTypeShowMode(string $raw): string
    {
        $v = strtolower(trim($raw));
        return in_array($v, ['both', 'drop_off', 'self_service'], true) ? $v : 'both';
    }

    /**
     * @param array<string,mixed> $orderTypeDef
     */
    private function isOrderTypeVisibleInMode(array $orderTypeDef, string $orderMode): bool
    {
        $mode = $this->normalizeOrderTypeShowMode((string) ($orderTypeDef['show_in_order_mode'] ?? 'both'));
        if ($mode === 'both') {
            return true;
        }
        return $mode === $orderMode;
    }

    private function getInventoryItemByCategory(PDO $pdo, int $tenantId, int $itemId, string $category): ?array
    {
        if ($itemId < 1) {
            return null;
        }
        $st = $pdo->prepare(
            'SELECT id, name, category, unit_cost
             FROM laundry_inventory_items
             WHERE tenant_id = ? AND id = ? AND category = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $itemId, $category]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param 'washer'|'dryer' $expectedKind
     * @return array<string, mixed>|null
     */
    private function fetchAvailableMachineByKind(PDO $pdo, int $tenantId, int $machineId, string $expectedKind): ?array
    {
        if ($machineId < 1) {
            return null;
        }
        $st = $pdo->prepare(
            'SELECT id, machine_label, machine_kind, machine_type, credit_required, credit_balance, status
             FROM laundry_machines
             WHERE tenant_id = ? AND id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $machineId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row === null || (string) ($row['status'] ?? '') !== 'available') {
            return null;
        }
        if ((string) ($row['machine_kind'] ?? '') !== $expectedKind) {
            return null;
        }
        if (! $this->machineAllowedInFreeMode($pdo, $tenantId, $machineId, $expectedKind)) {
            return null;
        }

        return $row;
    }

    private function resolveOrderMachineType(?array $washer, ?array $dryer): string
    {
        foreach ([$washer, $dryer] as $machine) {
            if (! is_array($machine)) {
                continue;
            }
            $type = strtolower(trim((string) ($machine['machine_type'] ?? '')));
            if ($type === 'c5') {
                return 'c5';
            }
        }
        foreach ([$washer, $dryer] as $machine) {
            if (! is_array($machine)) {
                continue;
            }
            $type = strtolower(trim((string) ($machine['machine_type'] ?? 'manual')));
            if ($type !== '') {
                return $type;
            }
        }

        return 'manual';
    }

    /**
     * @param list<array<string,mixed>> $machines
     * @return list<array<string,mixed>>
     */
    private function filterFreeModeMachineRows(array $machines): array
    {
        if (! Auth::isTenantFreePlanRestricted(Auth::user())) {
            return $machines;
        }
        $limits = [
            'washer' => self::FREE_LIMIT_WASHERS,
            'dryer' => self::FREE_LIMIT_DRYERS,
        ];
        $seen = ['washer' => 0, 'dryer' => 0];
        $out = [];
        foreach ($machines as $machine) {
            $kind = strtolower(trim((string) ($machine['machine_kind'] ?? '')));
            if (! isset($limits[$kind])) {
                continue;
            }
            if ($seen[$kind] >= $limits[$kind]) {
                continue;
            }
            $seen[$kind]++;
            $out[] = $machine;
        }

        return $out;
    }

    private function machineAllowedInFreeMode(PDO $pdo, int $tenantId, int $machineId, string $kind): bool
    {
        if (! Auth::isTenantFreePlanRestricted(Auth::user())) {
            return true;
        }
        $limit = $kind === 'dryer' ? self::FREE_LIMIT_DRYERS : self::FREE_LIMIT_WASHERS;
        $st = $pdo->prepare(
            'SELECT id
             FROM laundry_machines
             WHERE tenant_id = ? AND machine_kind = ?
             ORDER BY CASE WHEN status = "available" THEN 0 ELSE 1 END ASC,
                      CASE WHEN credit_required = 1 AND credit_balance <= 0 THEN 1 ELSE 0 END ASC,
                      machine_label ASC, id ASC
             LIMIT '.$limit
        );
        $st->execute([$tenantId, $kind]);
        $allowedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $st->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return in_array($machineId, $allowedIds, true);
    }

    private function hasUsersFoldingFeeColumn(PDO $pdo): bool
    {
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'folding_fee_per_load'");
            return $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getBranchFoldServiceAmount(PDO $pdo, int $tenantId): float
    {
        if ($tenantId < 1) {
            return 0.0;
        }
        try {
            $st = $pdo->prepare(
                'SELECT fold_service_amount
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return 0.0;
            }

            return max(0.0, (float) ($row['fold_service_amount'] ?? 0));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function getBranchFoldCommissionTarget(PDO $pdo, int $tenantId): string
    {
        if ($tenantId < 1) {
            return 'branch';
        }
        try {
            $st = $pdo->prepare(
                'SELECT fold_commission_target
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $v = strtolower(trim((string) ($row['fold_commission_target'] ?? 'branch')));

            return in_array($v, ['staff', 'branch'], true) ? $v : 'branch';
        } catch (\Throwable) {
            return 'branch';
        }
    }

    private function getBranchPayrollCutoffDays(PDO $pdo, int $tenantId): int
    {
        if ($tenantId < 1) {
            return 15;
        }
        try {
            $st = $pdo->prepare(
                'SELECT payroll_cutoff_days
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return max(1, min(31, (int) ($row['payroll_cutoff_days'] ?? 15)));
        } catch (\Throwable) {
            return 15;
        }
    }

    private function getBranchPayrollHoursPerDay(PDO $pdo, int $tenantId): float
    {
        if ($tenantId < 1) {
            return 8.0;
        }
        try {
            $st = $pdo->prepare(
                'SELECT payroll_hours_per_day
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return max(1.0, min(24.0, (float) ($row['payroll_hours_per_day'] ?? 8)));
        } catch (\Throwable) {
            return 8.0;
        }
    }

    private function getBranchBoolConfig(PDO $pdo, int $tenantId, string $column, bool $default): bool
    {
        return (bool) $this->getBranchScalarConfig($pdo, $tenantId, $column, $default ? 1 : 0);
    }

    private function persistBranchBluetoothPrintConfig(PDO $pdo, int $tenantId, bool $enabled): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'enable_bluetooth_print')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, enable_bluetooth_print, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE enable_bluetooth_print = VALUES(enable_bluetooth_print), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    private function persistBranchTrackGasulUsageConfig(PDO $pdo, int $tenantId, bool $enabled): void
    {
        if ($tenantId < 1 || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'track_gasul_usage')) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, track_gasul_usage, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE track_gasul_usage = VALUES(track_gasul_usage), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    /** @return array{inclusion_mode:string,fold_mode:string,order_type_codes:array<int,string>} */
    private function getBranchKioskAutomationSettings(PDO $pdo, int $tenantId): array
    {
        $inclusionMode = strtolower(trim((string) $this->getBranchScalarConfig($pdo, $tenantId, 'kiosk_inclusion_autofill_mode', 'off')));
        if (! in_array($inclusionMode, ['off', 'lock', 'editable'], true)) {
            $inclusionMode = 'off';
        }
        $foldMode = strtolower(trim((string) $this->getBranchScalarConfig($pdo, $tenantId, 'kiosk_fold_autofill_mode', 'off')));
        if (! in_array($foldMode, ['off', 'free_fold', 'fold_with_price'], true)) {
            $foldMode = 'off';
        }
        $rawCodes = (string) $this->getBranchScalarConfig($pdo, $tenantId, 'kiosk_autofill_order_type_codes', '');
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $v): string => strtolower(trim($v)),
            explode(',', $rawCodes)
        ), static fn (string $v): bool => $v !== '')));

        return [
            'inclusion_mode' => $inclusionMode,
            'fold_mode' => $foldMode,
            'order_type_codes' => $codes,
        ];
    }

    /** @return array{inclusion_mode:string,fold_mode:string,order_type_codes:array<int,string>} */
    private function parseKioskAutomationSettingsFromRequest(Request $request): array
    {
        $inclusionMode = $request->boolean('kiosk_inclusion_autofill_lock')
            ? 'lock'
            : ($request->boolean('kiosk_inclusion_autofill_editable') ? 'editable' : 'off');
        $foldMode = $request->boolean('kiosk_fold_autofill_free_fold')
            ? 'free_fold'
            : ($request->boolean('kiosk_fold_autofill_fold_with_price') ? 'fold_with_price' : 'off');
        $raw = $request->input('kiosk_autofill_order_type_codes', []);
        $codes = is_array($raw) ? $raw : [];
        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => strtolower(trim((string) $v)),
            $codes
        ), static fn (string $v): bool => $v !== '')));

        return [
            'inclusion_mode' => $inclusionMode,
            'fold_mode' => $foldMode,
            'order_type_codes' => $codes,
        ];
    }

    /** @param array{inclusion_mode:string,fold_mode:string,order_type_codes:array<int,string>} $settings */
    private function persistBranchKioskAutomationSettings(PDO $pdo, int $tenantId, array $settings): void
    {
        if ($tenantId < 1
            || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'kiosk_inclusion_autofill_mode')
            || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'kiosk_fold_autofill_mode')
            || ! $this->hasColumn($pdo, 'laundry_branch_configs', 'kiosk_autofill_order_type_codes')) {
            return;
        }
        $inclusionMode = in_array((string) ($settings['inclusion_mode'] ?? 'off'), ['off', 'lock', 'editable'], true)
            ? (string) $settings['inclusion_mode']
            : 'off';
        $foldMode = in_array((string) ($settings['fold_mode'] ?? 'off'), ['off', 'free_fold', 'fold_with_price'], true)
            ? (string) $settings['fold_mode']
            : 'off';
        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => strtolower(trim((string) $v)),
            $settings['order_type_codes'] ?? []
        ), static fn (string $v): bool => $v !== '')));
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, kiosk_inclusion_autofill_mode, kiosk_fold_autofill_mode, kiosk_autofill_order_type_codes, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                kiosk_inclusion_autofill_mode = VALUES(kiosk_inclusion_autofill_mode),
                kiosk_fold_autofill_mode = VALUES(kiosk_fold_autofill_mode),
                kiosk_autofill_order_type_codes = VALUES(kiosk_autofill_order_type_codes),
                updated_at = NOW()'
        )->execute([$tenantId, $inclusionMode, $foldMode, implode(',', $codes)]);
    }

    private function getBranchIntConfig(PDO $pdo, int $tenantId, string $column, int $default): int
    {
        return max(0, (int) $this->getBranchScalarConfig($pdo, $tenantId, $column, $default));
    }

    private function getBranchFloatConfig(PDO $pdo, int $tenantId, string $column, float $default): float
    {
        return max(0.0, (float) $this->getBranchScalarConfig($pdo, $tenantId, $column, $default));
    }

    private function getBranchScalarConfig(PDO $pdo, int $tenantId, string $column, mixed $default): mixed
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasColumn($pdo, 'laundry_branch_configs', $column)) {
            return $default;
        }
        try {
            $st = $pdo->prepare('SELECT `'.$column.'` FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $value = $st->fetchColumn();

            return $value === false ? $default : $value;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table) || ! preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        try {
            // Use exact COLUMN_NAME match: SHOW COLUMNS ... LIKE treats '_' as a single-char wildcard in MySQL.
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $st->execute([$table, $column]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{rendered_seconds:int,pay_units:float,approved_ot_hours:float} */
    private function computePayrollAttendanceTotals(PDO $pdo, int $tenantId, int $userId, string $from, string $to, float $hoursPerDay, bool $otActive): array
    {
        $st = $pdo->prepare(
            'SELECT clock_in_at, COALESCE(clock_out_at, NOW()) AS clock_out_at, overtime_status
             FROM laundry_time_logs
             WHERE tenant_id = ? AND user_id = ? AND clock_in_at BETWEEN ? AND ?'
        );
        $st->execute([$tenantId, $userId, $from.' 00:00:00', $to.' 23:59:59']);
        $renderedSeconds = 0;
        $payUnits = 0.0;
        $approvedOtHours = 0.0;
        $requiredSeconds = max(1, (int) round($hoursPerDay * 3600));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $in = strtotime((string) ($row['clock_in_at'] ?? ''));
            $out = strtotime((string) ($row['clock_out_at'] ?? ''));
            if ($in === false || $out === false || $out <= $in) {
                continue;
            }
            $seconds = $out - $in;
            $renderedSeconds += $seconds;
            if ($seconds + 1 < ($requiredSeconds * 0.5)) {
                $unit = 0.0;
            } elseif ($seconds + 1 < $requiredSeconds) {
                $unit = 0.5;
            } else {
                $unit = 1.0;
            }
            $payUnits += $unit;
            if ($otActive && (string) ($row['overtime_status'] ?? '') === 'approved') {
                $otSeconds = max(0, $seconds - $requiredSeconds);
                if ($otSeconds >= 1800) {
                    $whole = (int) floor($otSeconds / 3600);
                    $rem = $otSeconds % 3600;
                    $approvedOtHours += $whole + ($rem >= 1800 ? 0.5 : 0.0);
                }
            }
        }

        return [
            'rendered_seconds' => $renderedSeconds,
            'pay_units' => $payUnits,
            'approved_ot_hours' => $approvedOtHours,
        ];
    }

    private function classifyAttendanceSeconds(int $seconds, float $hoursPerDay): string
    {
        $required = max(1, (int) round($hoursPerDay * 3600));
        if ($seconds + 1 < $required * 0.5) {
            return 'Not counted';
        }
        if ($seconds + 1 < $required) {
            return 'Half-day';
        }

        return 'Full-day';
    }

    private function computeBranchCommissionPool(PDO $pdo, int $tenantId, string $from, string $to, int $dailyQuota, float $ratePerLoad): float
    {
        if ($dailyQuota < 1 || $ratePerLoad <= 0) {
            return 0.0;
        }
        $st = $pdo->prepare(
            'SELECT DATE(o.created_at) AS order_day, COALESCE(SUM(GREATEST(1, o.wash_qty)), 0) AS load_count
             FROM laundry_orders o
             INNER JOIN laundry_order_types t ON t.tenant_id = o.tenant_id AND t.code = o.order_type
             WHERE o.tenant_id = ?
               AND o.created_at BETWEEN ? AND ?
               AND COALESCE(o.is_void, 0) = 0
               AND o.status <> "void"
               AND COALESCE(o.is_free, 0) = 0
               AND COALESCE(o.is_reward, 0) = 0
               AND t.service_kind IN ("full_service", "wash_only", "rinse_only")
               AND o.status = "paid"
             GROUP BY DATE(o.created_at)'
        );
        $st->execute([$tenantId, $from.' 00:00:00', $to.' 23:59:59']);
        $pool = 0.0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $loads = max(0, (int) ($row['load_count'] ?? 0));
            $excess = max(0, $loads - $dailyQuota);
            $pool += $excess * $ratePerLoad;
        }

        return $pool;
    }

    /** @return list<int> */
    private function parseWorkDaysCsv(string $csv): array
    {
        $parts = array_filter(array_map('trim', explode(',', $csv)), static fn ($v): bool => $v !== '');
        $days = [];
        foreach ($parts as $p) {
            $n = (int) $p;
            if ($n >= 1 && $n <= 7) {
                $days[] = $n;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);

        return $days === [] ? [1, 2, 3, 4, 5, 6, 7] : $days;
    }

    private function countScheduledDaysInRange(string $from, string $to, array $workDays): int
    {
        $start = strtotime($from.' 00:00:00');
        $end = strtotime($to.' 00:00:00');
        if ($start === false || $end === false || $start > $end) {
            return 0;
        }
        $set = array_flip($workDays);
        $count = 0;
        for ($ts = $start; $ts <= $end; $ts = strtotime('+1 day', $ts) ?: ($end + 1)) {
            $dow = (int) date('N', $ts);
            if (isset($set[$dow])) {
                $count++;
            }
        }

        return $count;
    }

    private function workDaysLabel(array $workDays): string
    {
        $labels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $out = [];
        foreach ($workDays as $d) {
            if (isset($labels[$d])) {
                $out[] = $labels[$d];
            }
        }
        return $out === [] ? 'Mon-Sun' : implode(', ', $out);
    }

    private function scalar(PDO $pdo, string $sql, array $params): mixed
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchColumn();
    }

    /**
     * When a paid, stamp-earning order is voided, remove the reward load that was granted at payment
     * (prevents paying then voiding to farm stamps). Idempotent via void_reversal event per order.
     *
     * @param  array<string, mixed>  $order
     */
    private function reverseRewardEarnForVoidedOrderIfApplicable(PDO $pdo, int $tenantId, int $orderId, array $order, int $actorUserId): void
    {
        try {
            if ((int) ($order['is_free'] ?? 0) === 1 || (int) ($order['is_reward'] ?? 0) === 1) {
                return;
            }
            if ((string) ($order['payment_status'] ?? '') !== 'paid') {
                return;
            }
            $customerId = (int) ($order['customer_id'] ?? 0);
            if ($customerId < 1) {
                return;
            }

            $dup = $pdo->prepare(
                'SELECT id FROM laundry_reward_events WHERE tenant_id = ? AND order_id = ? AND event_type = ? LIMIT 1'
            );
            $dup->execute([$tenantId, $orderId, 'void_reversal']);
            if ($dup->fetch(PDO::FETCH_ASSOC)) {
                return;
            }

            $sumSt = $pdo->prepare(
                'SELECT COALESCE(SUM(points_delta), 0) FROM laundry_reward_events WHERE tenant_id = ? AND order_id = ? AND event_type = ?'
            );
            $sumSt->execute([$tenantId, $orderId, 'earned']);
            $earned = (float) $sumSt->fetchColumn();
            if ($earned <= 1e-9) {
                return;
            }

            $pto = $pdo->prepare(
                'SELECT id, points_balance, lifetime_earned FROM laundry_customer_points WHERE tenant_id = ? AND customer_id = ? FOR UPDATE'
            );
            $pto->execute([$tenantId, $customerId]);
            $pt = $pto->fetch(PDO::FETCH_ASSOC);
            if (! is_array($pt)) {
                return;
            }

            $balance = (float) ($pt['points_balance'] ?? 0);
            $life = (float) ($pt['lifetime_earned'] ?? 0);
            $newBalance = max(0.0, $balance - $earned);
            $newLife = max(0.0, $life - $earned);

            $pdo->prepare(
                'UPDATE laundry_customer_points SET points_balance = ?, lifetime_earned = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$newBalance, $newLife, (int) ($pt['id'] ?? 0)]);

            $cfgSt = $pdo->prepare('SELECT id, reward_order_type_code FROM laundry_reward_configs WHERE tenant_id = ? LIMIT 1');
            $cfgSt->execute([$tenantId]);
            $cfg = $cfgSt->fetch(PDO::FETCH_ASSOC);
            $rewardConfigId = is_array($cfg) && (int) ($cfg['id'] ?? 0) > 0 ? (int) $cfg['id'] : null;
            $rewardOrderTypeCode = is_array($cfg) ? ($cfg['reward_order_type_code'] ?? null) : null;

            $pdo->prepare(
                'INSERT INTO laundry_reward_events (tenant_id, customer_id, order_id, event_type, points_delta, balance_after, reward_config_id, reward_order_type_code, actor_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $tenantId,
                $customerId,
                $orderId,
                'void_reversal',
                -$earned,
                $newBalance,
                $rewardConfigId,
                $rewardOrderTypeCode !== '' ? $rewardOrderTypeCode : null,
                $actorUserId > 0 ? $actorUserId : null,
            ]);
        } catch (\PDOException $e) {
            $state = (string) ($e->errorInfo[0] ?? '');
            if ($state === '42S02' || str_contains(strtolower($e->getMessage()), 'base table or view not found')) {
                return;
            }
            throw $e;
        }
    }

    private function rewardProgramIsActive(PDO $pdo, int $tenantId): bool
    {
        try {
            $st = $pdo->prepare('SELECT is_active FROM laundry_reward_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) && (int) ($row['is_active'] ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /** When $isUpdate is true and rewards are inactive, returns null so the stored flag is not changed. */
    private function resolveIncludeInRewardsForSave(PDO $pdo, int $tenantId, string $serviceKind, Request $request, bool $isUpdate): ?int
    {
        if (! $this->rewardProgramIsActive($pdo, $tenantId)) {
            if ($isUpdate) {
                return null;
            }

            return $serviceKind === 'full_service' ? 1 : 0;
        }

        return $request->boolean('include_in_rewards') ? 1 : 0;
    }

    private function orderTypeEarnsRewardPoints(?array $orderType): bool
    {
        if (! is_array($orderType)) {
            return false;
        }
        if (array_key_exists('include_in_rewards', $orderType)) {
            return (int) ($orderType['include_in_rewards'] ?? 0) === 1;
        }

        return (string) ($orderType['service_kind'] ?? '') === 'full_service';
    }

    /** Reward "loads" for earning: one logical load per wash/dry pair (full service stores the same N in both columns). */
    private function rewardLoadUnitsFromOrderRow(?array $orderRow): int
    {
        if (! is_array($orderRow)) {
            return 1;
        }
        $w = max(0, (int) ($orderRow['wash_qty'] ?? 0));
        $d = max(0, (int) ($orderRow['dry_minutes'] ?? 0));

        return max(1, max($w, $d));
    }

    private function rewardLoadUnitsForConfiguredOrderType(PDO $pdo, int $tenantId, ?int $orderId, string $rewardOrderTypeCode, ?array $fallbackOrderRow): int
    {
        $code = trim($rewardOrderTypeCode);
        if ($tenantId < 1 || $code === '') {
            return 0;
        }
        if ($orderId !== null && $orderId > 0 && $this->hasTable($pdo, 'laundry_order_lines')) {
            try {
                $qty = (float) ($this->scalar(
                    $pdo,
                    'SELECT COALESCE(SUM(quantity), 0)
                     FROM laundry_order_lines
                     WHERE tenant_id = ? AND order_id = ? AND order_type_code = ?',
                    [$tenantId, $orderId, $code]
                ) ?: 0.0);
                if ($qty > 0) {
                    return max(1, (int) round($qty));
                }
            } catch (\Throwable) {
                // Fallback below for older schemas or unavailable order lines.
            }
        }
        $fallbackCode = strtolower(trim((string) ($fallbackOrderRow['order_type'] ?? '')));
        if ($fallbackCode !== '' && $fallbackCode === strtolower($code)) {
            return $this->rewardLoadUnitsFromOrderRow($fallbackOrderRow);
        }

        return 0;
    }

    private function applyRewardsCounter(PDO $pdo, int $tenantId, ?int $customerId, ?array $orderType, ?int $orderId = null, ?int $actorUserId = null, ?array $orderRowForLoads = null): void
    {
        if ($customerId === null || $customerId < 1) {
            return;
        }
        if ($orderId !== null && $orderId > 0) {
            $dupEarn = $pdo->prepare(
                'SELECT id
                 FROM laundry_reward_events
                 WHERE tenant_id = ? AND order_id = ? AND event_type = "earned"
                 LIMIT 1'
            );
            $dupEarn->execute([$tenantId, $orderId]);
            if ($dupEarn->fetch(PDO::FETCH_ASSOC)) {
                return;
            }
        }

        $cfgSt = $pdo->prepare('SELECT * FROM laundry_reward_configs WHERE tenant_id = ? LIMIT 1');
        $cfgSt->execute([$tenantId]);
        $cfg = $cfgSt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($cfg) || ! (bool) ($cfg['is_active'] ?? false)) {
            return;
        }
        $rewardOrderTypeCode = trim((string) ($cfg['reward_order_type_code'] ?? ''));
        if ($rewardOrderTypeCode !== '') {
            $loadUnits = $this->rewardLoadUnitsForConfiguredOrderType($pdo, $tenantId, $orderId, $rewardOrderTypeCode, $orderRowForLoads);
            if ($loadUnits < 1) {
                return;
            }
        } else {
            if (! $this->orderTypeEarnsRewardPoints($orderType)) {
                return;
            }
            $loadUnits = $this->rewardLoadUnitsFromOrderRow($orderRowForLoads);
        }
        $perLoad = max(0.0, (float) ($cfg['points_per_dropoff_load'] ?? 1));
        $delta = round($perLoad * $loadUnits, 4);
        if ($delta <= 1e-9) {
            return;
        }

        $pdo->prepare(
            'INSERT INTO laundry_customer_points (tenant_id, customer_id, points_balance, lifetime_earned, lifetime_redeemed, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                points_balance = points_balance + VALUES(points_balance),
                lifetime_earned = lifetime_earned + VALUES(lifetime_earned),
                updated_at = NOW()'
        )->execute([$tenantId, $customerId, $delta, $delta]);

        $balance = (float) ($this->scalar(
            $pdo,
            'SELECT points_balance FROM laundry_customer_points WHERE tenant_id = ? AND customer_id = ? LIMIT 1',
            [$tenantId, $customerId]
        ) ?: 0);
        $pdo->prepare(
            'INSERT INTO laundry_reward_events (tenant_id, customer_id, order_id, event_type, points_delta, balance_after, reward_config_id, reward_order_type_code, actor_user_id, created_at)
             VALUES (?, ?, ?, "earned", ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $tenantId,
            $customerId,
            $orderId,
            $delta,
            $balance,
            (int) ($cfg['id'] ?? 0) ?: null,
            $cfg['reward_order_type_code'] ?? null,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
        ]);
    }

    private function fetchRewardConfigForRedemption(PDO $pdo, int $tenantId): ?array
    {
        $st = $pdo->prepare(
            'SELECT *
             FROM laundry_reward_configs
             WHERE tenant_id = ? AND is_active = 1
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $cfg = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($cfg)) {
            return null;
        }
        $code = trim((string) ($cfg['reward_order_type_code'] ?? ''));
        if ($code === '') {
            return null;
        }

        return $cfg;
    }

    private function redeemRewardForOrder(PDO $pdo, int $tenantId, int $customerId, int $orderId, array $cfg, int $userId): void
    {
        $cost = max(1.0, (float) ($cfg['reward_points_cost'] ?? 10));
        $min = max(1.0, (float) ($cfg['minimum_points_to_redeem'] ?? $cost));
        $ptSt = $pdo->prepare(
            'SELECT id, points_balance
             FROM laundry_customer_points
             WHERE tenant_id = ? AND customer_id = ?
             FOR UPDATE'
        );
        $ptSt->execute([$tenantId, $customerId]);
        $pt = $ptSt->fetch(PDO::FETCH_ASSOC);
        if (! is_array($pt)) {
            throw new \RuntimeException('Customer has no available reward balance.');
        }
        $balance = (float) ($pt['points_balance'] ?? 0);
        if ($balance < $min || $balance < $cost) {
            throw new \RuntimeException('Customer does not have enough reward balance.');
        }
        $balanceAfter = $balance - $cost;
        $pdo->prepare(
            'UPDATE laundry_customer_points
             SET points_balance = points_balance - ?, lifetime_redeemed = lifetime_redeemed + ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$cost, $cost, (int) ($pt['id'] ?? 0)]);

        $rewardName = trim((string) ($cfg['reward_name'] ?? 'Reward'));
        if ($rewardName === '') {
            $rewardName = 'Reward';
        }
        $rewardOrderTypeCode = trim((string) ($cfg['reward_order_type_code'] ?? ''));
        $pdo->prepare(
            'INSERT INTO laundry_reward_redemptions (tenant_id, customer_id, reward_name, order_id, reward_order_type_code, points_used, redeemed_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$tenantId, $customerId, $rewardName, $orderId, $rewardOrderTypeCode, $cost, $userId > 0 ? $userId : null]);
        $pdo->prepare(
            'INSERT INTO laundry_reward_events (tenant_id, customer_id, order_id, event_type, points_delta, balance_after, reward_config_id, reward_order_type_code, actor_user_id, created_at)
             VALUES (?, ?, ?, "redeemed", ?, ?, ?, ?, ?, NOW())'
        )->execute([$tenantId, $customerId, $orderId, -$cost, $balanceAfter, (int) ($cfg['id'] ?? 0), $rewardOrderTypeCode !== '' ? $rewardOrderTypeCode : null, $userId > 0 ? $userId : null]);
    }

    private function deductInventory(PDO $pdo, int $tenantId, array $deductionMap): void
    {
        $st = $pdo->prepare(
            'UPDATE laundry_inventory_items
             SET stock_quantity = GREATEST(0, stock_quantity - ?), updated_at = NOW()
             WHERE tenant_id = ? AND name = ?'
        );
        foreach ($deductionMap as $itemName => $quantity) {
            if ($quantity <= 0) {
                continue;
            }
            $st->execute([(float) $quantity, $tenantId, $itemName]);
        }
    }

    /**
     * Lock rows and ensure stock covers the sale. Call inside an open transaction.
     *
     * @param  array<int, float>  $deductionByItemId
     */
    private function assertSufficientInventoryForSale(PDO $pdo, int $tenantId, array $deductionByItemId): ?string
    {
        if ($deductionByItemId === []) {
            return null;
        }
        $lockSt = $pdo->prepare(
            'SELECT id, name, stock_quantity
             FROM laundry_inventory_items
             WHERE tenant_id = ? AND id = ?
             FOR UPDATE'
        );
        foreach ($deductionByItemId as $itemId => $needRaw) {
            $need = (float) $needRaw;
            if ($need <= 0) {
                continue;
            }
            $lockSt->execute([$tenantId, (int) $itemId]);
            $row = $lockSt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return 'Inventory item not found (ID '.(int) $itemId.').';
            }
            $stock = (float) ($row['stock_quantity'] ?? 0);
            if ($stock + 1e-9 < $need) {
                $name = (string) ($row['name'] ?? 'Item');
                $needStr = $this->formatInventoryQtyDisplay($need);
                $haveStr = $this->formatInventoryQtyDisplay($stock);

                return 'Insufficient stock for '.$name.': need '.$needStr.', on hand '.$haveStr.'.';
            }
        }

        return null;
    }

    private function findGasulOtherAddonItem(PDO $pdo, int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        try {
            $showItemInCondition = $this->hasColumn($pdo, 'laundry_inventory_items', 'show_item_in')
                ? 'AND COALESCE(show_item_in, "both") IN ("addon", "both")'
                : '';
            $st = $pdo->prepare(
                'SELECT id, name, category, unit_cost, stock_quantity
                 FROM laundry_inventory_items
                 WHERE tenant_id = ?
                   AND category = "other"
                   AND LOWER(TRIM(name)) = "gasul"
                   '.$showItemInCondition.'
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatInventoryQtyDisplay(float $q): string
    {
        if (abs($q - round($q)) < 1e-9) {
            return (string) (int) round($q);
        }

        return rtrim(rtrim(sprintf('%.4f', $q), '0'), '.');
    }

    private function deductInventoryByItemId(PDO $pdo, int $tenantId, array $deductionByItemId): void
    {
        if ($deductionByItemId === []) {
            return;
        }
        $st = $pdo->prepare(
            'UPDATE laundry_inventory_items
             SET stock_quantity = stock_quantity - ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        foreach ($deductionByItemId as $itemId => $quantity) {
            if ((float) $quantity <= 0) {
                continue;
            }
            $st->execute([(float) $quantity, $tenantId, (int) $itemId]);
        }
    }

    private function recordOrderInventoryMovements(PDO $pdo, int $tenantId, int $orderId, array $itemQtyMap, string $direction, ?string $note = null, ?int $actorUserId = null): void
    {
        if ($orderId < 1 || $itemQtyMap === []) {
            return;
        }
        if (! in_array($direction, ['deduct', 'restore'], true)) {
            $direction = 'deduct';
        }
        $st = $pdo->prepare(
            'INSERT INTO laundry_order_inventory_movements
             (tenant_id, order_id, inventory_item_id, direction, quantity, note, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        foreach ($itemQtyMap as $itemId => $qtyRaw) {
            $qty = (float) $qtyRaw;
            if ($qty <= 0) {
                continue;
            }
            $st->execute([$tenantId, $orderId, (int) $itemId, $direction, $qty, $note, $actorUserId]);
        }
    }

    private function restoreInventoryForVoidedOrder(PDO $pdo, int $tenantId, int $orderId, ?int $actorUserId = null): void
    {
        if ($orderId < 1) {
            return;
        }
        $pending = [];
        if ($this->hasTable($pdo, 'laundry_order_inventory_movements')) {
            $agg = $pdo->prepare(
                'SELECT inventory_item_id,
                        SUM(CASE WHEN direction = "deduct" THEN quantity WHEN direction = "restore" THEN -quantity ELSE 0 END) AS pending_qty
                 FROM laundry_order_inventory_movements
                 WHERE tenant_id = ? AND order_id = ?
                 GROUP BY inventory_item_id
                 HAVING pending_qty > 0'
            );
            $agg->execute([$tenantId, $orderId]);
            foreach ($agg->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $itemId = (int) ($row['inventory_item_id'] ?? 0);
                $qty = (float) ($row['pending_qty'] ?? 0);
                if ($itemId > 0 && $qty > 0) {
                    $pending[$itemId] = $qty;
                }
            }
        }
        if ($pending === []) {
            $pending = $this->buildLegacyDeductionMapForOrder($pdo, $tenantId, $orderId);
        }
        if ($pending === []) {
            return;
        }
        $st = $pdo->prepare(
            'UPDATE laundry_inventory_items
             SET stock_quantity = stock_quantity + ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        foreach ($pending as $itemId => $qty) {
            if ((float) $qty <= 0) {
                continue;
            }
            $st->execute([(float) $qty, $tenantId, (int) $itemId]);
        }
        if ($this->hasTable($pdo, 'laundry_order_inventory_movements')) {
            $this->recordOrderInventoryMovements($pdo, $tenantId, $orderId, $pending, 'restore', 'Void inventory restore', $actorUserId);
        }
    }

    private function buildLegacyDeductionMapForOrder(PDO $pdo, int $tenantId, int $orderId): array
    {
        $st = $pdo->prepare(
            'SELECT o.id, o.order_type, o.inclusion_detergent_item_id, o.inclusion_fabcon_item_id, o.inclusion_bleach_item_id
             FROM laundry_orders o
             WHERE o.tenant_id = ? AND o.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $orderId]);
        $order = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($order)) {
            return [];
        }
        $ot = $this->fetchOrderTypeByCode($pdo, $tenantId, (string) ($order['order_type'] ?? ''));
        $map = [];
        $incDetId = (int) ($order['inclusion_detergent_item_id'] ?? 0);
        $incFabId = (int) ($order['inclusion_fabcon_item_id'] ?? 0);
        $incBleachId = (int) ($order['inclusion_bleach_item_id'] ?? 0);
        $detQty = max(0.0, (float) ($ot['detergent_qty'] ?? 0));
        $fabQty = max(0.0, (float) ($ot['fabcon_qty'] ?? 0));
        $bleachQty = max(0.0, (float) ($ot['bleach_qty'] ?? 0));
        if ($incDetId > 0 && $detQty > 0) {
            $map[$incDetId] = ($map[$incDetId] ?? 0.0) + $detQty;
        }
        if ($incFabId > 0 && $fabQty > 0) {
            $map[$incFabId] = ($map[$incFabId] ?? 0.0) + $fabQty;
        }
        if ($incBleachId > 0 && $bleachQty > 0) {
            $map[$incBleachId] = ($map[$incBleachId] ?? 0.0) + $bleachQty;
        }
        $ao = $pdo->prepare(
            'SELECT ao.item_name, ao.quantity, i.id AS inventory_item_id
             FROM laundry_order_add_ons ao
             INNER JOIN laundry_inventory_items i ON i.tenant_id = ao.tenant_id AND i.name = ao.item_name
             WHERE ao.tenant_id = ? AND ao.order_id = ?'
        );
        $ao->execute([$tenantId, $orderId]);
        foreach ($ao->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $itemId = (int) ($row['inventory_item_id'] ?? 0);
            $qty = max(0.0, (float) ($row['quantity'] ?? 0));
            if ($itemId > 0 && $qty > 0) {
                $map[$itemId] = ($map[$itemId] ?? 0.0) + $qty;
            }
        }

        return $map;
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                 LIMIT 1'
            );
            $st->execute([$table]);
            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyLoadCardUsage(PDO $pdo, int $tenantId, string $machineType, string $serviceKind, int $washQty): void
    {
        if (strtolower($machineType) !== 'c5') {
            return;
        }
        if (! in_array($serviceKind, ['full_service', 'wash_only', 'rinse_only'], true)) {
            return;
        }

        $usage = max(1, $washQty);
        $pdo->prepare(
            'INSERT INTO laundry_load_cards (tenant_id, machine_type, balance, created_at, updated_at)
             VALUES (?, "c5", 1000, NOW(), NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()'
        )->execute([$tenantId]);
        $pdo->prepare(
            'UPDATE laundry_load_cards
             SET balance = GREATEST(0, balance - ?), updated_at = NOW()
             WHERE tenant_id = ? AND machine_type = "c5"'
        )->execute([$usage, $tenantId]);
    }

    /**
     * @param list<array<string,mixed>|null> $machines
     */
    private function deductMachineCredits(PDO $pdo, int $tenantId, array $machines, int $washQty, ?int $orderId = null): void
    {
        $usage = max(1, $washQty);
        $seen = [];
        $st = $pdo->prepare(
            'UPDATE laundry_machines
             SET credit_balance = GREATEST(0, credit_balance - ?), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND credit_required = 1'
        );
        foreach ($machines as $machine) {
            if (! is_array($machine)) {
                continue;
            }
            $id = (int) ($machine['id'] ?? 0);
            if ($id < 1 || isset($seen[$id]) || (int) ($machine['credit_required'] ?? 0) !== 1) {
                continue;
            }
            $seen[$id] = true;
            $st->execute([$usage, $tenantId, $id]);
            $this->recordMachineCreditMovement(
                $pdo,
                $tenantId,
                $id,
                'deduct',
                (float) $usage,
                $orderId,
                'Machine usage credit deduction',
                (int) (Auth::user()['id'] ?? 0)
            );
        }
    }

    private function restoreMachineCreditsForOrder(PDO $pdo, int $tenantId, int $orderId): void
    {
        if ($tenantId < 1 || $orderId < 1) {
            return;
        }
        $pendingByMachine = [];
        try {
            $st = $pdo->prepare(
                'SELECT machine_id,
                        SUM(CASE WHEN direction = "deduct" THEN amount WHEN direction = "restock" THEN -amount ELSE 0 END) AS pending_amount
                 FROM laundry_machine_credit_movements
                 WHERE tenant_id = ? AND order_id = ?
                 GROUP BY machine_id
                 HAVING pending_amount > 0'
            );
            $st->execute([$tenantId, $orderId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $machineId = (int) ($row['machine_id'] ?? 0);
                $amount = (float) ($row['pending_amount'] ?? 0);
                if ($machineId > 0 && $amount > 0) {
                    $pendingByMachine[$machineId] = $amount;
                }
            }
        } catch (\Throwable) {
            return;
        }
        if ($pendingByMachine === []) {
            return;
        }
        $update = $pdo->prepare(
            'UPDATE laundry_machines
             SET credit_balance = credit_balance + ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $actorId = (int) (Auth::user()['id'] ?? 0);
        foreach ($pendingByMachine as $machineId => $amount) {
            $update->execute([round($amount, 4), $tenantId, (int) $machineId]);
            $this->recordMachineCreditMovement(
                $pdo,
                $tenantId,
                (int) $machineId,
                'restock',
                (float) $amount,
                $orderId,
                'Status revert to Pending credit restore',
                $actorId > 0 ? $actorId : null
            );
        }
    }

    private function recordMachineCreditMovement(
        PDO $pdo,
        int $tenantId,
        int $machineId,
        string $direction,
        float $amount,
        ?int $orderId = null,
        ?string $note = null,
        ?int $createdByUserId = null
    ): void {
        if ($tenantId < 1 || $machineId < 1 || $amount <= 0) {
            return;
        }
        $dir = strtolower(trim($direction));
        if (! in_array($dir, ['deduct', 'restock'], true)) {
            return;
        }
        try {
            $pdo->prepare(
                'INSERT INTO laundry_machine_credit_movements
                 (tenant_id, machine_id, order_id, direction, amount, note, created_by_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $tenantId,
                $machineId,
                ($orderId !== null && $orderId > 0) ? $orderId : null,
                $dir,
                round($amount, 4),
                $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
                ($createdByUserId !== null && $createdByUserId > 0) ? $createdByUserId : null,
            ]);
        } catch (\Throwable) {
        }
    }

    private function storeInventoryImageFromUpload(Request $request, int $tenantId, ?string &$error = null): string
    {
        $error = null;
        $file = $request->files['image_file'] ?? null;
        if (! is_array($file)) {
            return '';
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($err !== UPLOAD_ERR_OK) {
            $error = match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image is too large. Please upload a smaller file.',
                UPLOAD_ERR_PARTIAL => 'Image upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server cannot write uploaded image.',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by a server extension.',
                default => 'Image upload failed. Please choose a valid image and try again.',
            };

            return '';
        }
        if ($tmp === '' || (! is_uploaded_file($tmp) && ! is_file($tmp))) {
            $error = 'Uploaded image temporary file is not available. Please try again.';

            return '';
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) {
            $error = 'Image must be between 1 byte and 5 MB.';

            return '';
        }
        $imgMeta = @getimagesize($tmp);
        if (! is_array($imgMeta) || empty($imgMeta['mime'])) {
            $error = 'Uploaded file is not a valid image.';

            return '';
        }
        $width = (int) ($imgMeta[0] ?? 0);
        $height = (int) ($imgMeta[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000) {
            $error = 'Image dimensions are invalid or too large.';

            return '';
        }
        $mime = (string) (mime_content_type($tmp) ?: '');
        if ($mime === '') {
            $mime = (string) $imgMeta['mime'];
        }
        $extFromMime = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => '',
        };
        $origName = strtolower((string) ($file['name'] ?? ''));
        $origExt = pathinfo($origName, PATHINFO_EXTENSION);
        $ext = $extFromMime !== '' ? $extFromMime : match ($origExt) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => '',
        };
        if ($ext === '') {
            $error = 'Only JPG, PNG, or WEBP images are allowed.';

            return '';
        }
        $dir = dirname(__DIR__, 3).'/public/uploads/laundry-inventory';
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $error = 'Could not create upload directory.';

            return '';
        }
        if (! is_writable($dir)) {
            $error = 'Upload directory is not writable.';

            return '';
        }
        $name = sprintf('tenant-%d-item-%s.%s', $tenantId, bin2hex(random_bytes(8)), $ext);
        $dest = $dir.'/'.$name;
        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : @rename($tmp, $dest);
        if (! $moved) {
            $moved = @copy($tmp, $dest);
        }
        if (! $moved || ! is_file($dest)) {
            $error = 'Could not save uploaded image to storage.';

            return '';
        }

        return 'uploads/laundry-inventory/'.$name;
    }

    private function hasLaundryInventoryImagePath(PDO $pdo): bool
    {
        if (self::$hasLaundryInventoryImagePath !== null) {
            return self::$hasLaundryInventoryImagePath;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_inventory_items` LIKE 'image_path'");
            self::$hasLaundryInventoryImagePath = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryInventoryImagePath = false;
        }

        return self::$hasLaundryInventoryImagePath;
    }

    private function ensureLaundryInventoryImagePathColumn(PDO $pdo): bool
    {
        if ($this->hasLaundryInventoryImagePath($pdo)) {
            return true;
        }
        try {
            $pdo->exec('ALTER TABLE `laundry_inventory_items` ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `unit_cost`');
        } catch (\Throwable) {
        }
        self::$hasLaundryInventoryImagePath = null;

        return $this->hasLaundryInventoryImagePath($pdo);
    }

    private function hasIncomingUpload(Request $request, string $key): bool
    {
        $file = $request->files[$key] ?? null;
        if (! is_array($file)) {
            return false;
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        return $err !== UPLOAD_ERR_NO_FILE;
    }

    private function generateOrderReferenceCandidate(?int $orderId = null): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $max = strlen($alphabet) - 1;
        $prefix = 'tx';
        if ($orderId !== null && $orderId > 0) {
            $prefix .= (string) $orderId;
        } else {
            $prefix .= date('His');
        }
        $code = $prefix;
        for ($i = 0; $i < 4; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    private function generateOrderGroupReferenceCandidate(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $max = strlen($alphabet) - 1;
        $code = 'grp'.date('ymdHis');
        for ($i = 0; $i < 4; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    private function resolveUniqueOrderReference(PDO $pdo, int $tenantId, string $preferred): string
    {
        if (! $this->hasLaundryOrdersReferenceCode($pdo)) {
            return $this->generateOrderReferenceCandidate();
        }
        $preferred = strtolower(trim($preferred));
        $preferred = preg_replace('/[^a-z0-9]/', '', $preferred) ?? '';
        $isValidPreferred = (bool) preg_match('/^[a-z0-9]{6,32}$/', $preferred);
        $check = $pdo->prepare(
            'SELECT 1
             FROM laundry_orders
             WHERE tenant_id = ? AND reference_code = ?
             LIMIT 1'
        );
        if ($isValidPreferred) {
            $check->execute([$tenantId, $preferred]);
            if ($check->fetch(PDO::FETCH_ASSOC) === false) {
                return $preferred;
            }
        }
        for ($i = 0; $i < 50; $i++) {
            $candidate = $this->generateOrderReferenceCandidate();
            $check->execute([$tenantId, $candidate]);
            if ($check->fetch(PDO::FETCH_ASSOC) === false) {
                return $candidate;
            }
        }

        return $this->generateOrderReferenceCandidate();
    }

    private function hasLaundryOrdersReferenceCode(PDO $pdo): bool
    {
        if (self::$hasLaundryOrdersReferenceCode !== null) {
            return self::$hasLaundryOrdersReferenceCode;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'reference_code'");
            self::$hasLaundryOrdersReferenceCode = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryOrdersReferenceCode = false;
        }

        return self::$hasLaundryOrdersReferenceCode;
    }

    private function hasLaundryOrdersGroupReferenceCode(PDO $pdo): bool
    {
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'group_reference_code'");
            return $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasLaundryOrdersDiscountPercentage(PDO $pdo): bool
    {
        if (self::$hasLaundryOrdersDiscountPercentage !== null) {
            return self::$hasLaundryOrdersDiscountPercentage;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'discount_percentage'");
            self::$hasLaundryOrdersDiscountPercentage = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryOrdersDiscountPercentage = false;
        }

        return self::$hasLaundryOrdersDiscountPercentage;
    }

    private function hasLaundryOrdersDiscountAmount(PDO $pdo): bool
    {
        if (self::$hasLaundryOrdersDiscountAmount !== null) {
            return self::$hasLaundryOrdersDiscountAmount;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'discount_amount'");
            self::$hasLaundryOrdersDiscountAmount = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryOrdersDiscountAmount = false;
        }

        return self::$hasLaundryOrdersDiscountAmount;
    }

    private function hasLaundryOrdersAmountTendered(PDO $pdo): bool
    {
        if (self::$hasLaundryOrdersAmountTendered !== null) {
            return self::$hasLaundryOrdersAmountTendered;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'amount_tendered'");
            self::$hasLaundryOrdersAmountTendered = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryOrdersAmountTendered = false;
        }

        return self::$hasLaundryOrdersAmountTendered;
    }

    private function hasLaundryOrdersChangeAmount(PDO $pdo): bool
    {
        if (self::$hasLaundryOrdersChangeAmount !== null) {
            return self::$hasLaundryOrdersChangeAmount;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'change_amount'");
            self::$hasLaundryOrdersChangeAmount = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryOrdersChangeAmount = false;
        }

        return self::$hasLaundryOrdersChangeAmount;
    }

    private function ensureLaundryOrdersReferenceCode(PDO $pdo): bool
    {
        if ($this->hasLaundryOrdersReferenceCode($pdo)) {
            return true;
        }
        try {
            $pdo->exec('ALTER TABLE `laundry_orders` ADD COLUMN `reference_code` VARCHAR(32) NULL DEFAULT NULL AFTER `id`');
        } catch (\Throwable) {
        }
        self::$hasLaundryOrdersReferenceCode = null;
        if (! $this->hasLaundryOrdersReferenceCode($pdo)) {
            return false;
        }
        try {
            $pdo->exec('ALTER TABLE `laundry_orders` ADD UNIQUE KEY `laundry_orders_tenant_reference_unique` (`tenant_id`, `reference_code`)');
        } catch (\Throwable) {
        }

        return true;
    }

    private function ensureLaundryOrdersGroupReferenceCode(PDO $pdo): bool
    {
        if ($this->hasLaundryOrdersGroupReferenceCode($pdo)) {
            return true;
        }
        try {
            $pdo->exec('ALTER TABLE `laundry_orders` ADD COLUMN `group_reference_code` VARCHAR(40) NULL DEFAULT NULL AFTER `reference_code`');
        } catch (\Throwable) {
        }
        return $this->hasLaundryOrdersGroupReferenceCode($pdo);
    }
}

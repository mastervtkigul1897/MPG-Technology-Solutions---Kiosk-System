<?php
$cards = [
    'Daily' => [$daily_sales, $daily_expense, $daily_profit],
    'Weekly' => [$weekly_sales, $weekly_expense, $weekly_profit],
    'Monthly' => [$monthly_sales, $monthly_expense, $monthly_profit],
    'Yearly' => [$yearly_sales, $yearly_expense, $yearly_profit],
];
?>
<div class="row g-3">
    <?php foreach ($cards as $label => [$sales, $expense, $profit]): ?>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6><?= e((string) $label) ?></h6>
                    <div>Sales: <strong><?= number_format((float) $sales, 2) ?></strong></div>
                    <div>Expenses: <strong><?= number_format((float) $expense, 2) ?></strong></div>
                    <div>Profit: <strong><?= number_format((float) $profit, 2) ?></strong></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

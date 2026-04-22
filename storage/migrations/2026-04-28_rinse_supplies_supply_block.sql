-- Optional fabric conditioner (1×) for rinse-only order types via supply_block = rinse_supplies.
UPDATE `laundry_order_types`
SET `supply_block` = 'rinse_supplies'
WHERE `code` = 'rinse_only' AND `service_kind` = 'rinse_only';

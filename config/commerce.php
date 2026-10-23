<?php

return [
    'email_notifications' => env('TRANSACTION_EMAIL_ENABLED', false),
    'permissions' => ['dashboard', 'catalog.read', 'catalog.write', 'inventory.read', 'inventory.write', 'orders.read', 'orders.cancel', 'payments.read', 'payments.verify', 'cod.confirm', 'shipping.write', 'customers.read', 'promotions.write', 'content.write', 'content.approve', 'settings.write', 'users.write', 'reports.read', 'cases.write', 'audit.read'],
    'roles' => [
        'admin' => ['*'],
        'management' => ['dashboard', 'catalog.read', 'inventory.read', 'orders.read', 'payments.read', 'customers.read', 'reports.read', 'audit.read'],
        'finance' => ['dashboard', 'orders.read', 'payments.read', 'payments.verify', 'inventory.read', 'reports.read', 'audit.read'],
        'warehouse' => ['dashboard', 'catalog.read', 'inventory.read', 'inventory.write', 'orders.read', 'shipping.write', 'audit.read'],
        'cs' => ['dashboard', 'catalog.read', 'inventory.read', 'orders.read', 'cod.confirm', 'orders.cancel', 'customers.read', 'cases.write', 'audit.read'],
    ],
];

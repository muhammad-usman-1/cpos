<?php

namespace App\Enums;

enum Permission: string
{
    case ManageUsers = 'users.manage';
    case ManageRoles = 'roles.manage';
    case ManageSettings = 'settings.manage';
    case ViewAudit = 'audit.view';
    case ViewCost = 'cost.view';
    case DeleteRecords = 'records.delete';
    case ManageMasters = 'masters.manage';
    case ViewProducts = 'products.view';
    case ManageProducts = 'products.manage';
    case ImportProducts = 'products.import';
    case AdjustStock = 'stock.adjust';
    case TakeStock = 'stock.take';
    case ViewSuppliers = 'suppliers.view';
    case ManageSuppliers = 'suppliers.manage';
    case PaySuppliers = 'suppliers.pay';
    case ViewPurchases = 'purchases.view';
    case ManagePurchases = 'purchases.manage';
    case CancelPurchases = 'purchases.cancel';
    case ReturnPurchases = 'purchases.return';
    case ViewCustomers = 'customers.view';
    case ManageCustomers = 'customers.manage';
    case ReceivePayments = 'payments.receive';
    case ManageMeasurements = 'measurements.manage';
    case ConfigureMeasurements = 'measurements.configure';
    case CreateSales = 'sales.create';
    case ViewSales = 'sales.view';
    case ReturnSales = 'sales.return';
    case SellBelowMinimum = 'sales.below_min_price';
    case CloseDay = 'sales.close_day';
    case ViewOrders = 'orders.view';
    case ManageOrders = 'orders.manage';
    case UpdateOrderStatus = 'orders.update_status';
    case AssignTailors = 'orders.assign';
    case ViewAssignedOrders = 'orders.view_assigned';
    case ViewReceivables = 'receivables.view';
    case ManageExpenses = 'expenses.manage';
    case ViewCashBook = 'cashbook.view';
    case SendMessages = 'messages.send';

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => __('Create, edit, deactivate users and reset passwords'),
            self::ManageRoles => __('Edit role permissions'),
            self::ManageSettings => __('Change system settings'),
            self::ViewAudit => __('View audit log'),
            self::ViewCost => __('See cost prices, average cost and profit'),
            self::DeleteRecords => __('Delete products, suppliers and master records'),
            self::ManageMasters => __('Manage categories, brands, fabrics and colours'),
            self::ViewProducts => __('View products, stock and stock ledger'),
            self::ManageProducts => __('Add and edit products, print labels'),
            self::ImportProducts => __('Import products from CSV'),
            self::AdjustStock => __('Post manual stock adjustments'),
            self::TakeStock => __('Run and post physical stock counts'),
            self::ViewSuppliers => __('View suppliers and supplier ledger'),
            self::ManageSuppliers => __('Add and edit suppliers'),
            self::PaySuppliers => __('Record payments to suppliers'),
            self::ViewPurchases => __('View purchases and purchase data'),
            self::ManagePurchases => __('Create and edit purchases'),
            self::CancelPurchases => __('Cancel purchases'),
            self::ReturnPurchases => __('Record purchase returns'),
            self::ViewCustomers => __('Search and view customers and their history'),
            self::ManageCustomers => __('Add and edit customers and family profiles'),
            self::ReceivePayments => __('Receive payments from customers'),
            self::ManageMeasurements => __('Take and update measurements'),
            self::ConfigureMeasurements => __('Add, rename and remove measurement fields'),
            self::CreateSales => __('Make sales at the counter and hold bills'),
            self::ViewSales => __('View sales and print receipts'),
            self::ReturnSales => __('Record sale returns and exchanges'),
            self::SellBelowMinimum => __('Sell below the minimum sale price'),
            self::CloseDay => __('Close the day and count cash'),
            self::ViewOrders => __('View all stitching orders and the due list'),
            self::ManageOrders => __('Create, edit, deliver and cancel stitching orders'),
            self::UpdateOrderStatus => __('Change stitching order status'),
            self::AssignTailors => __('Assign orders to tailors and receive pieces'),
            self::ViewAssignedOrders => __('View orders assigned to me (tailors)'),
            self::ViewReceivables => __('See receivables, ageing and customer statements'),
            self::ManageExpenses => __('Record and cancel shop expenses'),
            self::ViewCashBook => __('View the cash book and bank book'),
            self::SendMessages => __('Send SMS / WhatsApp messages and view the message log'),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ManageUsers, self::ManageRoles, self::ManageSettings, self::ViewAudit, self::ViewCost, self::DeleteRecords => 'Administration',
            self::ManageMasters, self::ViewProducts, self::ManageProducts, self::ImportProducts, self::AdjustStock, self::TakeStock => 'Products and stock',
            self::ViewSuppliers, self::ManageSuppliers, self::PaySuppliers => 'Suppliers',
            self::ViewPurchases, self::ManagePurchases, self::CancelPurchases, self::ReturnPurchases => 'Purchases',
            self::ViewCustomers, self::ManageCustomers, self::ReceivePayments, self::ManageMeasurements, self::ConfigureMeasurements => 'Customers and measurements',
            self::CreateSales, self::ViewSales, self::ReturnSales, self::SellBelowMinimum, self::CloseDay => 'Sales',
            self::ViewOrders, self::ManageOrders, self::UpdateOrderStatus, self::AssignTailors, self::ViewAssignedOrders => 'Stitching orders',
            self::ViewReceivables, self::ManageExpenses, self::ViewCashBook => 'Payments and accounts',
            self::SendMessages => 'Notifications',
        };
    }

    /**
     * Default permissions granted to each seeded role. The owner role bypasses all checks.
     *
     * @return array<string, list<self>>
     */
    public static function defaultsByRole(): array
    {
        return [
            // Shop-floor work: the counter, stitching orders and tailors. No stock buying, suppliers, cost or settings.
            'manager' => [
                self::ViewProducts,
                self::ViewCustomers, self::ManageCustomers, self::ReceivePayments, self::ManageMeasurements,
                self::CreateSales, self::ViewSales, self::ReturnSales, self::CloseDay,
                self::ViewOrders, self::ManageOrders, self::UpdateOrderStatus, self::AssignTailors,
                self::ViewReceivables, self::ManageExpenses, self::SendMessages,
            ],
            'salesman' => [
                self::ViewProducts,
                self::ViewCustomers, self::ManageCustomers, self::ReceivePayments, self::ManageMeasurements,
                self::CreateSales, self::ViewSales,
                self::ViewOrders, self::ManageOrders, self::UpdateOrderStatus,
            ],
            'tailor' => [
                self::ViewAssignedOrders, self::UpdateOrderStatus,
            ],
        ];
    }
}

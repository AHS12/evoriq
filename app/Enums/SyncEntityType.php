<?php

namespace App\Enums;

/**
 * The Clockify entities the sync pipeline can ingest (SYNC-01). Values mirror
 * Clockify's own entity names so they can travel through the change feed
 * (SYNC-06) unchanged.
 */
enum SyncEntityType: string
{
    case USER = 'USER';
    case PROJECTS = 'PROJECTS';
    case CLIENTS = 'CLIENTS';
    case TASKS = 'TASKS';
    case TAGS = 'TAGS';
    case TIME_ENTRY = 'TIME_ENTRY';
    case TIME_ENTRY_RATE = 'TIME_ENTRY_RATE';
    case TIME_ENTRY_CUSTOM_FIELD_VALUE = 'TIME_ENTRY_CUSTOM_FIELD_VALUE';
    case CUSTOM_FIELDS = 'CUSTOM_FIELDS';
    case USER_GROUPS = 'USER_GROUPS';
    case SCHEDULED_ASSIGNMENT = 'SCHEDULED_ASSIGNMENT';
    case HOLIDAYS = 'HOLIDAYS';
    case PTO_POLICY = 'PTO_POLICY';
    case TIME_OFF_REQUEST = 'TIME_OFF_REQUEST';
    case BALANCE = 'BALANCE';
    case APPROVAL_REQUESTS = 'APPROVAL_REQUESTS';
    case INVOICES = 'INVOICES';
    case WORKSPACE = 'WORKSPACE';

    public function label(): string
    {
        return match ($this) {
            self::USER => __('Users'),
            self::PROJECTS => __('Projects'),
            self::CLIENTS => __('Clients'),
            self::TASKS => __('Tasks'),
            self::TAGS => __('Tags'),
            self::TIME_ENTRY => __('Time entries'),
            self::TIME_ENTRY_RATE => __('Time entry rates'),
            self::TIME_ENTRY_CUSTOM_FIELD_VALUE => __('Time entry custom field values'),
            self::CUSTOM_FIELDS => __('Custom fields'),
            self::USER_GROUPS => __('User groups'),
            self::SCHEDULED_ASSIGNMENT => __('Scheduled assignments'),
            self::HOLIDAYS => __('Holidays'),
            self::PTO_POLICY => __('PTO policies'),
            self::TIME_OFF_REQUEST => __('Time off requests'),
            self::BALANCE => __('Balances'),
            self::APPROVAL_REQUESTS => __('Approval requests'),
            self::INVOICES => __('Invoices'),
            self::WORKSPACE => __('Workspace'),
        };
    }
}

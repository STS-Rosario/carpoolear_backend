<?php

namespace STS\Admin;

enum AdminPermission: string
{
    case DashboardView = 'admin.dashboard.view';
    case GraphsView = 'admin.graphs.view';
    case UsersSearch = 'admin.users.search';
    case UsersEdit = 'admin.users.edit';
    case UsersImpersonate = 'admin.users.impersonate';
    case UsersMigrate = 'admin.users.migrate';
    case UsersDeleteRequests = 'admin.users.delete_requests';
    case IdentityManualReview = 'admin.identity.manual.review';
    case IdentityMpReview = 'admin.identity.mp.review';
    case TripsView = 'admin.trips.view';
    case SupportTickets = 'admin.support.tickets';
    case AuditView = 'admin.audit.view';
    case UsersSuspend = 'admin.users.suspend';
    case UsersSetActive = 'admin.users.set_active';
    case UsersVerify = 'admin.users.verify';
    case UsersUnverify = 'admin.users.unverify';
    case UsersDelete = 'admin.users.delete';
    case UsersAnonymize = 'admin.users.anonymize';
    case UsersBanAndAnonymize = 'admin.users.ban_and_anonymize';
    case UsersDriverVerified = 'admin.users.driver_verified';
    case UsersBannedList = 'admin.users.banned_list';
    case IdentityManualPurge = 'admin.identity.manual.purge';
    case IdentityStats = 'admin.identity.stats';
    case TripsHide = 'admin.trips.hide';
    case TripsExcessContribution = 'admin.trips.excess_contribution';
    case RatingsEdit = 'admin.ratings.edit';
    case ReferencesEdit = 'admin.references.edit';
    case MaintenanceManage = 'admin.maintenance.manage';
    case ChangelogsManage = 'admin.changelogs.manage';
    case CarCatalog = 'admin.cars.catalog';
    case PulseView = 'admin.pulse.view';
    case BadgesManage = 'admin.badges.manage';
    case CampaignsManage = 'admin.campaigns.manage';
}

<?php
use STS\Http\Controllers\Api\v1\AuthController;
use STS\Http\Controllers\Api\v1\CarController;
use STS\Http\Controllers\Api\v1\ConversationController;
use STS\Http\Controllers\Api\v1\DeviceController;
use STS\Http\Controllers\Api\v1\FriendsController;
use STS\Http\Controllers\Api\v1\NotificationController;
use STS\Http\Controllers\Api\v1\PassengerController;
use STS\Http\Controllers\Api\v1\RatingController;
use STS\Http\Controllers\Api\v1\ReferencesController;
use STS\Http\Controllers\Api\v1\RoutesController;
use STS\Http\Controllers\Api\v1\SocialController;
use STS\Http\Controllers\Api\v1\SubscriptionController;
use STS\Http\Controllers\Api\v1\TripController;
use STS\Http\Controllers\Api\v1\UserController;
use STS\Http\Controllers\Api\v1\MercadoPagoWebhookController;
use STS\Http\Controllers\Api\v1\MercadoPagoOAuthController;
use STS\Http\Controllers\Api\v1\ManualIdentityValidationController;
use STS\Http\Controllers\Api\v1\ManualValidationPaymentController;
use STS\Http\Controllers\Api\v1\DataController;
use STS\Http\Controllers\Api\Admin\BadgeController;
use STS\Http\Controllers\Api\Admin\CampaignController;
use STS\Http\Controllers\Api\Admin\CampaignMilestoneController;
use STS\Http\Controllers\Api\Admin\CampaignDonationController;
use STS\Http\Controllers\Api\Admin\CampaignRewardController;
use STS\Http\Controllers\Api\Admin\CarController as AdminCarController;
use STS\Http\Controllers\Api\Admin\UserController as AdminUserController;
use STS\Http\Controllers\Api\Admin\ManualIdentityValidationController as AdminManualIdentityValidationController;
use STS\Http\Controllers\Api\Admin\MercadoPagoRejectedValidationController as AdminMercadoPagoRejectedValidationController;
use STS\Http\Controllers\Api\v1\CampaignController as ApiCampaignController;
use STS\Http\Controllers\Api\v1\CampaignRewardController as ApiCampaignRewardController;


Route::middleware(['api'])->group(function () {

    Route::post('login', [AuthController::class, 'login']);
    Route::post('retoken', [AuthController::class, 'retoken']);
    Route::get('config', [AuthController::class, 'getConfig']);

    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('activate/{activation_token?}', [AuthController::class, 'active']);
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:password-reset');
    Route::post('change-password/{token?}', [AuthController::class, 'changePasswod']);
    Route::post('log', [AuthController::class, 'log']);

    // Mercado Pago OAuth callback (public; validated via state)
    Route::get('mercadopago/oauth/callback', [MercadoPagoOAuthController::class, 'callback']);
    // Manual validation payment success redirect (public)
    Route::get('mercadopago/manual-validation-success', [ManualValidationPaymentController::class, 'success']);

    Route::prefix('users')->group( function () {
        Route::get('/ratings', [RatingController::class,'ratings']);
        Route::get('/ratings/pending', [RatingController::class,'pendingRate']);
        Route::get('/get-trips', [TripController::class,'getTrips']);
        Route::get('/get-old-trips', [TripController::class,'getOldTrips']);
        Route::get('/my-trips', [TripController::class,'getTrips']);
        Route::get('/my-old-trips', [TripController::class,'getOldTrips']);
        Route::get('/requests', [PassengerController::class,'allRequests']);
        Route::get('/payment-pending', [PassengerController::class,'paymentPendingRequest']);
        Route::get('/sellado-viaje', [TripController::class, 'selladoViaje']);

        Route::get('/list', [UserController::class,'index']);
        Route::get('/search', [UserController::class,'searchUsers']);

        Route::post('/', [UserController::class,'create']);
        Route::get('/me', [UserController::class,'show']);
        Route::get('/{id}/badges', [UserController::class,'badges']);
        Route::get('/bank-data', [UserController::class,'bankData']);
        Route::get('/terms', [UserController::class,'terms']);
        Route::get('/mercadopago-oauth-url', [UserController::class, 'getMercadoPagoOAuthUrl']);
        Route::get('/manual-identity-validation-cost', [ManualIdentityValidationController::class, 'cost']);
        Route::get('/manual-identity-validation', [ManualIdentityValidationController::class, 'status']);
        Route::post('/manual-identity-validation/preference', [ManualIdentityValidationController::class, 'createPreference']);
        Route::post('/manual-identity-validation', [ManualIdentityValidationController::class, 'submit']);
        Route::get('/{name?}', [UserController::class,'show']);
        Route::get('/{id?}/ratings', [RatingController::class,'ratings']);
        Route::put('/', [UserController::class,'update']);
        Route::put('/modify', [UserController::class,'adminUpdate']);
        Route::put('/photo', [UserController::class,'updatePhoto']);
        Route::post('/donation', [UserController::class,'registerDonation']);
        Route::any('/change/{property?}/{value?}', [UserController::class,'changeBooleanProperty']);
        Route::post('/delete-account-request', [UserController::class,'deleteAccountRequest']);
        Route::post('/delete-account', [UserController::class,'deleteAccount']);
    });
 
    Route::prefix('notifications')->group( function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::delete('/{id?}', [NotificationController::class, 'delete']);
        Route::get('/count', [NotificationController::class, 'count']);
    });

    Route::prefix('friends')->group( function () {
        Route::post('/accept/{id?}', [FriendsController::class, 'accept']);
        Route::post('/request/{id?}', [FriendsController::class, 'request']);
        Route::post('/delete/{id?}', [FriendsController::class, 'delete']);
        Route::post('/reject/{id?}', [FriendsController::class, 'reject']);
         
        Route::get('/', [FriendsController::class, 'index']);
        Route::get('/pedings', [FriendsController::class, 'pedings']);
    });

    Route::prefix('social')->group( function () { 
        Route::post('/login/{provider?}', [SocialController::class, 'login']);
        Route::post('/friends/{provider?}', [SocialController::class, 'friends']);
        Route::put('/update/{provider?}', [SocialController::class, 'update']);
    });

    Route::prefix('trips')->group( function () {
        Route::get('/requests', [PassengerController::class, 'allRequests']);

        Route::get('/transactions', [PassengerController::class, 'transactions']);
        Route::get('/autocomplete', [RoutesController::class, 'autocomplete']);
        Route::get('/', [TripController::class, 'search']);
        Route::post('/', [TripController::class, 'create']);
        Route::put('/{id?}', [TripController::class, 'update']);
        Route::delete('/{id?}', [TripController::class, 'delete']);
        Route::get('/{id?}', [TripController::class, 'show']);
        Route::post('/{id?}/changeSeats', [TripController::class, 'changeTripSeats']);
        Route::post('/{id}/change-visibility', [TripController::class, 'changeVisibility']);
        Route::post('/price', [TripController::class, 'price']);
        Route::post('/trip-info', [TripController::class, 'getTripInfo']);
        
        Route::get('/{tripId}/passengers', [PassengerController::class, 'passengers']);
        Route::get('/{tripId}/requests', [PassengerController::class, 'requests']);

        Route::post('/{tripId}/requests', [PassengerController::class, 'newRequest']);
        Route::post('/{tripId}/requests/{userId}/cancel', [PassengerController::class, 'cancelRequest']);
        Route::post('/{tripId}/requests/{userId}/accept', [PassengerController::class, 'acceptRequest']);
        Route::post('/{tripId}/requests/{userId}/reject', [PassengerController::class, 'rejectRequest']);
        Route::post('/{tripId}/requests/{userId}/pay', [PassengerController::class, 'payRequest']);

        Route::post('/{tripId}/rate/{userId}', [RatingController::class, 'rate']);
        Route::post('/{tripId}/reply/{userId}', [RatingController::class, 'replay']);
    });

    Route::prefix('conversations')->group( function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'create']);
        Route::get('/user-list', [ConversationController::class, 'userList']);
        Route::get('/unread', [ConversationController::class, 'getMessagesUnread']);
        Route::get('/show/{id?}', [ConversationController::class, 'show']);

        Route::get('/{id?}', [ConversationController::class, 'getConversation']);
        Route::get('/{id?}/users', [ConversationController::class, 'users']);
        Route::post('/{id?}/users', [ConversationController::class, 'addUser']);
        Route::delete('/{id?}/users/{userId?}', [ConversationController::class, 'deleteUser']);
        Route::post('/{id?}/send', [ConversationController::class, 'send']);
        Route::post('/multi-send', [ConversationController::class, 'multiSend']);
    });

    Route::prefix('cars')->group( function () {
        Route::get('/', [CarController::class, 'index']);
        Route::post('/', [CarController::class, 'create']);
        Route::put('/{id?}', [CarController::class, 'update']);
        Route::delete('/{id?}', [CarController::class, 'delete']);
        Route::get('/{id?}', [CarController::class, 'show']);
    });

    Route::prefix('subscriptions')->group( function () { 
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::post('/', [SubscriptionController::class, 'create']);
        Route::put('/{id?}', [SubscriptionController::class, 'update']);
        Route::delete('/{id?}', [SubscriptionController::class, 'delete']);
        Route::get('/{id?}', [SubscriptionController::class, 'show']);
    });

    Route::prefix('devices')->group( function () {
        Route::get('/', [DeviceController::class,'index']);
        Route::post('/', [DeviceController::class,'register']);
        Route::put('/{id?}', [DeviceController::class,'update']);
        Route::delete('/{id?}', [DeviceController::class,'delete']);
        Route::post('/logout', [DeviceController::class,'logout']);
    });

    Route::prefix('data')->group( function () {
        Route::get('/trips', [DataController::class,'trips']);
        Route::get('/seats', [DataController::class,'seats']);
        Route::get('/users', [DataController::class,'users']);
        Route::get('/monthlyusers', [DataController::class,'monthlyUsers']);
    });

    // Public campaign routes
    Route::get('campaigns/{slug}', [ApiCampaignController::class, 'showBySlug']);

    Route::prefix('references')->group( function () {
        Route::post('/', [ReferencesController::class,'create']);
    });

    // Admin routes
    Route::prefix('admin')->middleware('user.admin')->group(function () {
        Route::apiResource('badges', BadgeController::class);
        // Campaign routes
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('campaigns.milestones', CampaignMilestoneController::class);
        Route::apiResource('campaigns.donations', CampaignDonationController::class);
        Route::apiResource('campaigns.rewards', CampaignRewardController::class);
        // Car management routes
        Route::apiResource('cars', AdminCarController::class);
        Route::get('users/account-delete-list', [AdminUserController::class, 'accountDeleteList']);
        Route::post('users/account-delete-update', [AdminUserController::class, 'accountDeleteUpdate']);
        Route::get('banned-users', [AdminUserController::class, 'bannedUsersList']);
        Route::post('users/{user}/delete', [AdminUserController::class, 'delete']);
        Route::post('users/{user}/anonymize', [AdminUserController::class, 'anonymize']);
        Route::post('users/{user}/ban-and-anonymize', [AdminUserController::class, 'banAndAnonymize']);
        Route::get('users/{user}/cars', [AdminCarController::class, 'userCars']);
        Route::post('users/{user}/cars', [AdminCarController::class, 'storeForUser']);
        // Manual identity validations
        Route::get('manual-identity-validations', [AdminManualIdentityValidationController::class, 'index']);
        Route::get('manual-identity-validations/{id}', [AdminManualIdentityValidationController::class, 'show']);
        Route::get('manual-identity-validations/{id}/image/{type}', [AdminManualIdentityValidationController::class, 'image']);
        Route::post('manual-identity-validations/{id}/review', [AdminManualIdentityValidationController::class, 'review']);
        Route::post('manual-identity-validations/{id}/purge', [AdminManualIdentityValidationController::class, 'purge']);
        // Mercado Pago rejected validations (OAuth validation failures)
        Route::get('mercado-pago-rejected-validations', [AdminMercadoPagoRejectedValidationController::class, 'index']);
        Route::get('mercado-pago-rejected-validations/{id}', [AdminMercadoPagoRejectedValidationController::class, 'show']);
        Route::post('mercado-pago-rejected-validations/{id}/approve', [AdminMercadoPagoRejectedValidationController::class, 'approve']);
    });

    Route::post('campaigns/{campaign}/rewards/{reward}/purchase', [ApiCampaignRewardController::class, 'purchase'])->middleware('logged.optional');
});
<?php

return [
    // Common
    'someone' => 'Someone',
    'date_not_available' => 'date not available',
    'destination_unknown' => 'unknown destination',

    // AcceptPassengerNotification
    'accept_passenger.title' => ':name has accepted your request.',
    'accept_passenger.message' => ':name has accepted your request.',

    // AnnouncementNotification
    'announcement.default_title' => 'Carpoolear Announcement',
    'announcement.default_message' => 'New Carpoolear announcement',

    // AutoCancelPassengerRequestIfRequestLimitedNotification
    'auto_cancel_passenger_request.title' => 'A passenger request has been automatically removed from your trip to :destination because they joined another trip with the same destination.',
    'auto_cancel_passenger_request.message' => 'A passenger request has been automatically removed from your trip to :destination because they joined another trip with the same destination.',

    // AutoCancelRequestIfRequestLimitedNotification
    'auto_cancel_request.title' => 'A request you made to the trip to :destination has been automatically removed because you joined another trip with the same destination.',
    'auto_cancel_request.message' => 'A request you made to the trip to :destination has been automatically removed because you joined another trip with the same destination.',

    // AutoRequestPassengerNotification
    'auto_request_passenger.title' => ':name wants to join one of your trips.',
    'auto_request_passenger.message' => ':name wants to join one of your trips.',

    // CancelPassengerNotification
    'cancel_passenger.driver_removed' => ':name has removed you from the trip',
    'cancel_passenger.passenger_left' => ':name has left the trip',

    // DeleteTripNotification
    'delete_trip.title' => ':name has deleted their trip.',
    'delete_trip.message' => ':name has deleted their trip.',

    // FriendAcceptNotification
    'friend_accept.title' => ':name has accepted your friend request.',
    'friend_accept.message' => ':name has accepted your friend request.',

    // FriendCancelNotification
    'friend_cancel.title' => ':name is no longer your friend',
    'friend_cancel.message' => ':name is no longer your friend',

    // FriendRejectNotification
    'friend_reject.title' => ':name has rejected your friend request.',
    'friend_reject.message' => ':name has rejected your friend request.',
    'friend_reject.status' => 'rejected',

    // FriendRequestNotification
    'friend_request.title' => 'New friend request',
    'friend_request.message' => ':name has sent you a friend request.',

    // HourLeftNotification
    'hour_left.title' => 'Trip reminder to :destination',
    'hour_left.message' => 'Remember that in just over an hour you are traveling to :destination',

    // NewMessageNotification
    'new_message.title' => ':name has sent you a message.',
    'new_message.message' => 'You have received new messages from :name.',
    'new_message.new_message' => 'New message',

    // NewUserNotification
    'new_user.title' => 'Welcome to Carpoolear!',
    'new_user.message' => 'Welcome to Carpoolear!',

    // PendingRateNotification
    'pending_rate.title' => 'Tell us how your trip to :destination went',
    'pending_rate.message' => 'You have a trip to rate.',

    // RejectPassengerNotification
    'reject_passenger.title' => ':name has rejected your request.',
    'reject_passenger.message' => ':name has rejected your request.',
    'reject_passenger.status' => 'rejected',

    // RequestNotAnswerNotification
    'request_not_answer.title' => 'One of your requests has not been answered yet',
    'request_not_answer.message' => 'Request from :name pending.',
    'request_not_answer.push_message' => 'One of your requests has not been answered yet',

    // RequestPassengerNotification
    'request_passenger.title' => ':name wants to join your trip.',
    'request_passenger.message' => ':name wants to join one of your trips.',

    // RequestRemainderNotification
    'request_remainder.title' => 'You have pending requests to answer.',
    'request_remainder.message' => 'You have pending requests to answer.',

    // ResetPasswordNotification
    'reset_password.title' => 'Password recovery for :app_name',
    'reset_password.message' => 'Password recovery',

    // SubscriptionMatchNotification
    'subscription_match.title' => 'We found a trip that matches your search',
    'subscription_match.message' => 'We found a trip that matches your search.',

    // UpdateTripNotification
    'update_trip.title' => ':name has changed the trip conditions.',
    'update_trip.message' => ':name has changed the conditions of their trip.',
];

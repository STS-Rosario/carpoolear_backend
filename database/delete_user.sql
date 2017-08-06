

delete from notifications where id in (select notification_id from notifications_params where value_type = 'STS\User' and value_id = 32);
delete from conversations  where id in (select conversation_id   from conversations_users where user_id = 32);
delete from users where id = 32;
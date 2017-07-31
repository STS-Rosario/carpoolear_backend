ALTER TABLE carpoolear.users add old_id bigint(20) not null;

# Query Migratoria de usuarios ----------------------------------------------------------
# Agregar PIN!!!!
INSERT IGNORE INTO carpoolear.users (
    name,
    email,  
    password,  
    terms_and_conditions,  
    birthday,  
    gender,  
    nro_doc,  
    description,  
    mobile_phone,  
    image,  
    banned,  
    is_admin,  
    active,  
    activation_token , 
    emails_notifications,  
    remember_token,  
    created_at,  
    updated_at,  
    last_connection,
    old_id 
) SELECT

    CASE WHEN name IS NOT NULL THEN name ELSE '' END,
    CASE WHEN email IS NOT NULL AND email <> '' THEN 
        email 
    ELSE  
        CONCAT(id, '@undefined') 
    END,
    null,
    terms_and_conditions,
    birthday,  
    gender,  
    CASE WHEN nro_doc IS NOT NULL THEN nro_doc ELSE '' END,  
    CASE WHEN descripcion IS NOT NULL THEN descripcion ELSE '' END,  
    CASE WHEN mobile_phone IS NOT NULL THEN mobile_phone ELSE '' END,
    '',
    0,
    es_admin,
    1,
    null,
    recibe_mail,
    null,
    created_at,
    updated_at,
    updated_at,
    id

FROM carpool_ear.users; 


# Accounts de facebook ----------------------------------------------------------
INSERT IGNORE INTO carpoolear.social_accounts (user_id, provider_user_id, provider, created_at, updated_at)
select id, old_id, 'facebook', created_at, updated_at  from carpoolear.users;

# Migrations de cars -----------------------------------------------------------
INSERT IGNORE INTO carpoolear.cars (patente, description, user_id, created_at, updated_at) 
select SUBSTRING(patente, 1, 10), '', 
    (select carpoolear.users.id as id from carpoolear.users where carpool_ear.users.id = carpoolear.users.old_id limit 1),
    NOW(), NOW()
from carpool_ear.users where carpool_ear.users.patente is not null;


# Migrations de trips -----------------------------------------------------------
alter table carpoolear.trips add old_id bigint(20) not null;
INSERT IGNORE INTO carpoolear.trips (
  user_id,
  from_town,
  to_town, 
  trip_date,
  description, 
  total_seats, 
  friendship_type_id,
  distance,
  estimated_time, 
  co2, 
  es_recurrente, 
  is_passenger, 
  mail_send, 
  enc_path, 
  created_at, 
  updated_at, 
  deleted_at, 
  return_trip_id, 
  car_id,
  old_id
) select 
    (select carpoolear.users.id as id from carpoolear.users where carpool_ear.trips.user_id = carpoolear.users.old_id limit 1) as uid,
    from_town,
    to_town,
    CASE WHEN DATE(trip_date) THEN trip_date  ELSE created_at END,
    description,
    total_seats,
    friendship_type_id,
    distance,
    estimated_time,
    co2,
    es_recurrente,
    CASE WHEN es_pasajero IS NOT NULL THEN es_pasajero ELSE false END ,
    CASE WHEN mail_send IS NOT NULL THEN mail_send ELSE true END ,
    '',
    CASE WHEN DATE(created_at) THEN created_at ELSE now() END,
    CASE WHEN DATE(updated_at) THEN updated_at ELSE now() END,
    null,
    null,
    (select carpoolear.cars.id as car_id from carpoolear.cars where carpoolear.cars.user_id = uid limit 1),
    id
from carpool_ear.trips where trip_date is not null  ;


#migrtions de passageros ------------------------------------------------
INSERT IGNORE INTO carpoolear.trip_passengers (
    user_id,
    trip_id,
    passenger_type,
    request_state,
    canceled_state,
    created_at,
    updated_at
) select 
    (select carpoolear.users.id as id from carpoolear.users where carpool_ear.trip_passengers.user_id = carpoolear.users.old_id limit 1) as uid,
    (select carpoolear.trips.id as tid from carpoolear.trips where carpool_ear.trip_passengers.trip_id = carpoolear.trips.old_id limit 1) as tid,
    passenger_type,
    request_state,
    0,
    CASE WHEN DATE(created_at) THEN created_at ELSE now() END,
    CASE WHEN DATE(updated_at) THEN updated_at ELSE now() END
from carpool_ear.trip_passengers where passenger_type = 1 ;

#migrtions de friends ---------------------------------
insert into carpoolear.friends (
    uid1,
    uid2,
    origin,
    state,
    created_at,
    updated_at
) select 
    u1.id,
    u2.id,
    'facebook',
    1,
    now(),
    now()
from carpool_ear.friends 
LEFT JOIN carpoolear.users as u1 ON uid1 = u1.old_id
LEFT JOIN carpoolear.users as u2 ON uid2 = u2.old_id
where u1.id is not null and u2.id is not null;


#migrtions de calificaciones ---------------------------------
INSERT IGNORE INTO carpoolear.rating (
    trip_id,
    user_id_from,
    user_id_to,
    user_to_type,
    user_to_state,
    rating,
    comment,
    reply_comment,
    reply_comment_created_at,
    voted,
    rate_at,
    voted_hash,
    created_at,
    updated_at
) select
    t.id,
    u1.id,
    u2.id,
    t.user_id <> to_id,
    1,
    puntuacion,
    comentario,
    '',
    null,
    1,
    carpool_ear.calificaciones.updated_at,
    '',
    carpool_ear.calificaciones.created_at,
    carpool_ear.calificaciones.updated_at
from carpool_ear.calificaciones
LEFT JOIN carpoolear.trips as t ON carpool_ear.calificaciones.trip_id = t.old_id 
LEFT JOIN carpoolear.users as u2 on to_id = u2.old_id
LEFT JOIN carpoolear.users as u1 on from_id = u1.old_id;

# Migrtions de mensajes ---------------------------------
alter table carpoolear.conversations add old_id bigint(20) not null;

INSERT IGNORE INTO carpoolear.conversations (
    type,
    title,
    trip_id,
    old_id
) select
    0,
    '',
    null,
    id
from carpool_ear.conversations;

# creo los users correspondiente a la conversacion
insert into carpoolear.conversations_users (
    conversation_id,
    user_id,
    `read`
) select 
    c.id,
    u.id,
    1
from carpool_ear.conversations
LEFT JOIN carpoolear.users as u on user_id = u.old_id
LEFT JOIN carpoolear.conversations as c on carpool_ear.conversations.id = c.old_id
where u.id is not null;

# Migrations de mensajes
INSERT IGNORE INTO carpoolear.messages (
    `text`,
    estado,
    user_id,
    conversation_id,
    created_at,
    updated_at
) select 
    mensaje,
    1,
    u.id,
    c.id,
    carpool_ear.messages.created_at,
    carpool_ear.messages.updated_at
from carpool_ear.messages
LEFT JOIN carpoolear.users as u on user_id = u.old_id
LEFT JOIN carpoolear.conversations as c on conversation_id = c.old_id
where u.id is not null;

INSERT IGNORE INTO carpoolear.user_message_read (
    user_id,
    message_id,
    `read`,
    created_at,
    updated_at
) select 
    cu.user_id,
    m.id,
    1,
    now(),
    now()
from carpoolear.messages as m
left join carpoolear.conversations_users as cu on m.conversation_id = cu.conversation_id and m.user_id <> cu.user_id;


alter table carpoolear.trips drop old_id;
alter table carpoolear.users drop old_id;
alter table carpoolear.conversations drop old_id;
alter table carpoolear5.users add old_id bigint(20) not null;

# Query Migratoria de usuarios ----------------------------------------------------------
# Agregar PIN!!!!
insert into carpoolear5.users (
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
) select  

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

from carpoolear_viejo.users; 


# Accounts de facebook ----------------------------------------------------------
insert into carpoolear5.social_accounts (user_id, provider_user_id, provider, created_at, updated_at)
select id, old_id, 'facebook', created_at, updated_at  from carpoolear5.users;

# Migrations de cars -----------------------------------------------------------
insert into carpoolear5.cars (patente, description, user_id, created_at, updated_at) 
select SUBSTRING(patente, 1, 10), '', 
    (select carpoolear5.users.id as id from carpoolear5.users where carpoolear_viejo.users.id = carpoolear5.users.old_id limit 1),
    NOW(), NOW()
from carpoolear_viejo.users where carpoolear_viejo.users.patente is not null;


# Migrations de trips -----------------------------------------------------------
alter table carpoolear5.trips add old_id bigint(20) not null;
insert into carpoolear5.trips (
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
    (select carpoolear5.users.id as id from carpoolear5.users where carpoolear_viejo.trips.user_id = carpoolear5.users.old_id limit 1) as uid,
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
    (select carpoolear5.cars.id as car_id from carpoolear5.cars where carpoolear5.cars.user_id = uid limit 1),
    id
from carpoolear_viejo.trips where trip_date is not null  ;


#migrtions de passageros ------------------------------------------------
insert into carpoolear5.trip_passengers (
    user_id,
    trip_id,
    passenger_type,
    request_state,
    canceled_state,
    created_at,
    updated_at
) select 
    (select carpoolear5.users.id as id from carpoolear5.users where carpoolear_viejo.trip_passengers.user_id = carpoolear5.users.old_id limit 1) as uid,
    (select carpoolear5.trips.id as tid from carpoolear5.trips where carpoolear_viejo.trip_passengers.trip_id = carpoolear5.trips.old_id limit 1) as tid,
    passenger_type,
    request_state,
    0,
    CASE WHEN DATE(created_at) THEN created_at ELSE now() END,
    CASE WHEN DATE(updated_at) THEN updated_at ELSE now() END
from carpoolear_viejo.trip_passengers where passenger_type = 1 ;

#migrtions de friends ---------------------------------
insert into carpoolear5.friends (
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
from carpoolear_viejo.friends 
LEFT JOIN carpoolear5.users as u1 ON uid1 = u1.old_id
LEFT JOIN carpoolear5.users as u2 ON uid2 = u2.old_id
where u1.id is not null and u2.id is not null;


#migrtions de calificaciones ---------------------------------
insert into carpoolear5.rating (
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
    substring(comentario,1,100),
    '',
    null,
    1,
    carpoolear_viejo.calificaciones.updated_at,
    '',
    carpoolear_viejo.calificaciones.created_at,
    carpoolear_viejo.calificaciones.updated_at
from carpoolear_viejo.calificaciones
LEFT JOIN carpoolear5.trips as t ON carpoolear_viejo.calificaciones.trip_id = t.old_id 
LEFT JOIN carpoolear5.users as u2 on to_id = u2.old_id
LEFT JOIN carpoolear5.users as u1 on from_id = u1.old_id;

# Migrtions de mensajes ---------------------------------
alter table carpoolear5.conversations add old_id bigint(20) not null;

insert into carpoolear5.conversations (
    type,
    title,
    trip_id,
    old_id
) select
    0,
    '',
    null,
    id
from carpoolear_viejo.conversations;

# creo los users correspondiente a la conversacion
insert into carpoolear5.conversations_users (
    conversation_id,
    user_id,
    `read`
) select 
    c.id,
    u.id,
    1
from carpoolear_viejo.conversations
LEFT JOIN carpoolear5.users as u on user_id = u.old_id
LEFT JOIN carpoolear5.conversations as c on carpoolear_viejo.conversations.id = c.old_id
where u.id is not null;

# Migrations de mensajes
insert into carpoolear5.messages (
    `text`,
    estado,
    user_id,
    conversation_id,
    created_at,
    updated_at
) select 
    substring(mensaje,1,100),
    1,
    u.id,
    c.id,
    carpoolear_viejo.messages.created_at,
    carpoolear_viejo.messages.updated_at
from carpoolear_viejo.messages
LEFT JOIN carpoolear5.users as u on user_id = u.old_id
LEFT JOIN carpoolear5.conversations as c on conversation_id = c.old_id
where u.id is not null;

insert into carpoolear5.user_message_read (
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
from carpoolear5.messages as m
left join carpoolear5.conversations_users as cu on m.conversation_id = cu.conversation_id and m.user_id <> cu.user_id;


alter table carpoolear5.trips drop old_id;
alter table carpoolear5.users drop old_id;
alter table carpoolear5.conversations drop old_id;
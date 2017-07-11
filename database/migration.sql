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

# Migrations de trips -----------------------------------------------------------

insert into carpoolear5.cars (patente, description, user_id, created_at, updated_at) 
select SUBSTRING(patente, 1, 10), '', 
    (select carpoolear5.users.id as id from carpoolear5.users where carpoolear_viejo.users.id = carpoolear5.users.old_id limit 1),
    NOW(), NOW()
from carpoolear_viejo.users where carpoolear_viejo.users.patente is not null;

alter table carpoolear5.users drop old_id;

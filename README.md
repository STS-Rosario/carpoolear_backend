# Carpoolear backend

Carpoolear es la primera aplicación argentina de Facebook que permite a los usuarios de dicha red social compartir viajes en automóvil con otros usuarios de su entorno.

Es una customización ad-hoc para Argentina de la filosofía carpooling, la cual consiste en compartir nuestros viajes en auto con otras personas de forma cotidiana. El carpooling es una práctica popular en Estados Unidos y Europa, donde se realiza de manera organizada para lograr aumentar el número de viajes compartidos y que estos sean concretados con otras personas además de nuestros vecinos y amigos.

## Carpoolear on Docker

1) Depending on your operating system, you may need to add permissions to these folders: 
    ```bash
    sudo chmod 777 -R storage/
    sudo chmod 777 -R public/
    ```

1) Building and running docker images: 
    ```bash
    docker-compose up
    ```

    NOTE: if you have trouble running Docker, try removing the `composer-install` y `database-seed-and-migrate` sections from `docker-compose.yml` and trying again.

1) Set your `.env` (use `.env.example` as an example)

1) (OPTIONAL: only if you removed `composer-install` y `database-seed-and-migrate` from `docker-compose.yml` in a previous step). Go to Docker UI, go to the `carpoolear_backend` container, enter the `Terminal` for that container, and execute the following commands:
    1) `composer update`
    1) `php artisan migrate`
    1) `php artisan db:seed --class=TestingSeeder`
    1) `php artisan config:clear`
    1) `php artisan georoute:build`

1) Now start your frontend and enjoy carpoolear!

___Docker compose file:___
You can start a develop environment with just one command with docker-compose:

```
docker-compose up -d
```

docker-compose.yml:

```
version: '2'

services:
  carpoolear_db:
    image: mysql
    container_name: carpoolear_db
    environment:
      MYSQL_DATABASE: carpoolear
      MYSQL_USER: carpoolear
      MYSQL_PASSWORD: carpoolear
      MYSQL_ROOT_PASSWORD: carpoolear
    volumes:
      - ./.db:/var/lib/mysql
    networks:
      - esnet 

  carpoolear_backend:
    build: ./backend
    container_name: carpoolear_backend
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
      SERVER_PORT: 8080
      DB_HOST: carpoolear_db
      DB_DATABASE: carpoolear
      DB_USERNAME: carpoolear
      DB_PASSWORD: carpoolear
      APP_KEY: qwertyuiopasdfghjklzxcvbnm123456
      JWT_KEY: qwertyuiopasdfghjklzxcvbnm123456
      API_PREFIX: api
      API_VERSION: v1
      MAIL_DRIVER: log
    ports:
      - 8080:8080
    volumes:
      - ./backend:/app
    networks:
      - esnet 
networks:
  esnet:  
```



## Start coding (old way)

Clone repository (remember to make your own fork)
```bash
git clone https://github.com/STS-Rosario/carpoolear_backend.git
```

Install dependencies
```bash
composer install
```
Configure the database access in the .env file
```bash
cp .env.example .env
```
Generate laravel key
```bash
php artisan key:generate
```

Give read/write access to the storage folder
```bash
chmod -R ugo+rw storage/
```

Generate the database
```bash
php artisan migrate
```

You will need to use a local webserver and point it to the public folder

Happy coding!

## Contributing


## Troubleshooting

```
[PDOException] - SQLSTATE[HY000] [2002] No such file or directory
```
 * check if the mysql server is running
 * change your .env file to DB_HOST=**127.0.0.1** instead of **localhost**

```
[PDOException]                                                                          
PDO::__construct(): The server requested authentication method unknown to the client [caching_sha2_password]
```
* create or alter your mysql user to use mysql_native_password

```sql
create user username@localhost identified with mysql_native_password by 'password';
```

```sql
alter user 'username'@'localhost' identified with mysql_native_password by 'password';
```

## Feature Flags

The application uses several feature flags to control different functionalities. These can be configured in your `.env` file:

### Module Flags

- `MODULE_COORDINATE_BY_MESSAGE`: Enables the ability to coordinate trip details through messages. When enabled, users can exchange specific trip coordination information through the messaging system.

- `MODULE_USER_REQUEST_LIMITED_ENABLED`: If enabled, the user will not be able to request a ride if there are already requests in the same trip date, FOR THE SAME DESTINATION and in the range of hours defined in MODULE_USER_REQUEST_LIMITED_HOURS_RANGE

- `MODULE_USER_REQUEST_LIMITED_HOURS_RANGE`: 12 hours before and after the trip date

- `MODULE_SEND_FULL_TRIP_MESSAGE`: If enabled, the app will send a message to the remaining passengers when the trip is full when accepting a passenger

- `MODULE_UNIQUE_DOC_PHONE`: If enabled, it will prevent users from registering with same phone or DNI than other users

- `MODULE_UNASWERED_MESSAGE_LIMIT`: If enabled, implements a limit on unanswered messages in conversations to maintain active communication between users

- `MODULE_TRIP_SEATS_PAYMENT`: If enabled, allows users to make payments for specific seats on a trip, implementing a payment system for trip reservations

- `MODULE_VALIDATED_DRIVERS`: If enabled, users must go through a verification process before they can create trips as drivers, adding a layer of safety and trust to the platform

## License

The Carpoolear backend is open-sourced software licensed under the [GPL 3.0](https://github.com/STS-Rosario/carpoolear_backend/blob/master/LICENSE).

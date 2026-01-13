# Carpoolear backend

Carpoolear es la primera aplicación argentina de Facebook que permite a los usuarios de dicha red social compartir viajes en automóvil con otros usuarios de su entorno.

Es una customización ad-hoc para Argentina de la filosofía carpooling, la cual consiste en compartir nuestros viajes en auto con otras personas de forma cotidiana. El carpooling es una práctica popular en Estados Unidos y Europa, donde se realiza de manera organizada para lograr aumentar el número de viajes compartidos y que estos sean concretados con otras personas además de nuestros vecinos y amigos.

## Start coding

Clone repository (remember to make your own fork)
```bash
git clone https://github.com/STS-Rosario/carpoolear_backend.git
```

Install dependencies
```bash
cd carpoolear_backend
composer update
composer install
```
Install MySQL, run the MySQL server, create a database and then
configure the database access in the .env file
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

(optional)
Seed the database with example data
```bash
php artisan db:seed --class=TestingSeeder
```

Generate Geo route nodes for autocomplete to work
```bash
php artisan georoute:build
php artisan nodesgeo:load - this one can take a long time
```

After .env changes, run these
```bash
php artisan config:clear
php artisan cache:clear
```

You will need to use a local webserver and point it to the public folder. You can use Herd, use 8.2 as the PHP version. This will create a site like carpoolear_backend and you'll be able to point the frontend to https://carpoolear_backend.test

### Notifications
For notifications to work, you need to run the worker: 

```bash
php artisan queue:work --daemon --tries=3 &
```

### Push Notifications
Push notifications need notifications working, and Firebase setup, which you'll need to create a project in Firebase and set the keys in the backend in frontend in the .env file

```bash
FIREBASE_JSON=firebase-service-account.json
FIREBASE_PROJECT_NAME=project-id
```

Also you'll need to add `firebase-service-account.json` from Firebase into `/storage/app.`

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
--- 


```
Problem: Creating a trip gives the error: A problem occurred while creating the trip. Please try again.
```
Solution: 
```
php artisan cache:table
php artisan migrate
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

- `MODULE_TRIP_CREATION_PAYMENT_ENABLED`: If enabled, the trip creator will be asked to pay a fee

- `MODULE_TRIP_CREATION_PAYMENT_AMOUNT_CENTS`: Amount in cents that the trip creator will have to pay if enabled

## License

The Carpoolear backend is open-sourced software licensed under the [GPL 3.0](https://github.com/STS-Rosario/carpoolear_backend/blob/master/LICENSE).

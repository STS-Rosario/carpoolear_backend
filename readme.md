# Carpoolear backend

Carpoolear es la primera aplicación argentina de Facebook que permite a los usuarios de dicha red social compartir viajes en automóvil con otros usuarios de su entorno.

Es una customización ad-hoc para Argentina de la filosofía carpooling, la cual consiste en compartir nuestros viajes en auto con otras personas de forma cotidiana. El carpooling es una práctica popular en Estados Unidos y Europa, donde se realiza de manera organizada para lograr aumentar el número de viajes compartidos y que estos sean concretados con otras personas además de nuestros vecinos y amigos.

## Start coding

Clone repository (remember to make your own fork)
```bash
git clone https://github.com/STS-Rosario/carpoolear_backend.git
```

Install dependcies
```bash
composer install
```
Generate laravel key
```bash
php artisan key:generate
```


Configure the database access in the .env file
```bash
cp .env.example .env
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



## License

The Carpoolear backend is open-sourced software licensed under the [GPL 3.0](https://github.com/STS-Rosario/carpoolear_backend/blob/master/LICENSE).

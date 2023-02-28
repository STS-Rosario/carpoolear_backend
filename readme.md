Este proyecto es una  copia de https://github.com/STS-Rosario/carpoolear_backend, en caso de querer colaborar ingrese al proyecto original.

# Actualizacion 28/2/23
Para inicializar el proyecto actualmente hay que ingresar los siguientes comandos:
```bash
git clone https://gitlab.com/medeiro/carpoolear
```
Iniciamos docker
```bash
docker-compose up -d
```
Instalamos dependencias
```bash
docker exec -it carpoolear_backend composer install
```
Creamos las tablas en la db
```bash
docker exec -it carpoolear_backend php artisan migrate
```
Instalamos datos de prueba
```bash
docker exec -it carpoolear_backend php artisan db:seed --class=TestingSeeder
```
El proyecto inicializara en local con puerto 8080 // 127.0.0.1:8080 y 127.0.0.1:81 el administrador de base de datos. con las siguientes credenciales:
```bash
 user:carpoolear
 pass:carpoolear
```

A partir de este momento el proyecto esta listo para trabajar :)

Despues de creado el proyecto solamente ingresar comando para inciar docker.
```bash
docker-compose up -d
```

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

## Carpoolear on Docker

1) Complete your environment file.

2) Building docker images: 
```
docker build -t carpoolear_backend .
```

3) Run image in a container: 
```
docker run --rm --name carpoolear_backend  -p 8080:8080 -d carpoolear_backend 
```

4) Seed database:

```
docker exec -it carpoolear_backend php artisan migrate
docker exec -it carpoolear_backend php artisan db:seed --class=TestingSeeder
```

5) Now start your frontend and enjoy carpoolear!

___Docker compose file:___
You can start a develp environment with just one command with docker-compose:

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

## License

The Carpoolear backend is open-sourced software licensed under the [GPL 3.0](https://github.com/STS-Rosario/carpoolear_backend/blob/master/LICENSE).

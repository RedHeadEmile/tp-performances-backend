## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : 30 secondes

**Choix des méthodes à analyser** :

- `getMeta` 4.30s
- `getMetas` 4.5s
- `getReviews` 9s



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 27.1 secondes

**Temps consommé par `getDB()`**

- **Avant** 1.23s

- **Après** 2ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux**

- **Avant** TEMPS

- **Après** TEMPS


#### Amélioration de la méthode `getMetas` et donc de la méthode `getMeta` :

- **Avant** 4.5s

```sql
SELECT * FROM wp_usermeta
```

- **Après** 1.56s

```sql
SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = ?
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 9s

```sql
SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 6.5s

```sql
SELECT COUNT(meta_value) as c, AVG(meta_value) as a FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review' GROUP BY wp_posts.post_author;
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 15.17s

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 230ms

```sql
SELECT  room.ID                   as roomid,
        room.post_title           as roomtitle,

        bathrooms_meta.meta_value as bathrooms,
        bedrooms_meta.meta_value  as bedrooms,
        img_meta.meta_value       as img,
        surface_meta.meta_value   as surface,
        type_meta.meta_value      as type,
        price_meta.meta_value     as price

FROM wp_posts as room
    INNER JOIN wp_postmeta as bathrooms_meta ON bathrooms_meta.post_id = room.ID AND bathrooms_meta.meta_key = 'bathrooms_count'
    INNER JOIN wp_postmeta as bedrooms_meta  ON bedrooms_meta.post_id = room.ID  AND bedrooms_meta.meta_key  = 'bedrooms_count'
    INNER JOIN wp_postmeta as img_meta       ON img_meta.post_id = room.ID       AND img_meta.meta_key       = 'coverImage'
    INNER JOIN wp_postmeta as surface_meta   ON surface_meta.post_id = room.ID   AND surface_meta.meta_key   = 'surface'
    INNER JOIN wp_postmeta as type_meta      ON type_meta.post_id = room.ID      AND type_meta.meta_key      = 'type'
    INNER JOIN wp_postmeta as price_meta     ON price_meta.post_id = room.ID     AND price_meta.meta_key     = 'price'

WHERE
      room.post_author          = :hotelId
  AND room.post_type          = 'room'

  AND CAST(bathrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bathrooms
  AND CAST(bedrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bedrooms

  AND CAST(price_meta.meta_value AS DECIMAL(10, 6)) >= :min_price
  AND CAST(price_meta.meta_value AS DECIMAL(10, 6)) < :max_price

  AND CAST(surface_meta.meta_value AS DECIMAL(10, 6)) >= :min_surface
  AND CAST(surface_meta.meta_value AS DECIMAL(10, 6)) < :max_surface

  AND type_meta.meta_value IN :types

GROUP BY room.ID
ORDER BY CAST(price_meta.meta_value AS DECIMAL(10, 6)) ASC
LIMIT 1;
```



## Question 5 : Réduction du nombre de requêtes SQL pour `getMetas`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201      | 601       |
| Temps de `getMetas`          | 1.59s     | 1.3s      |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 601       | 1         |
| Temps de chargement global   | 21.9s     | 4s        |

**Requête SQL**

```SQL
SELECT
    hotel.ID                        as hotel_id,
    hotel.display_name              as hotel_name,
    address_1_meta.meta_value       as hotel_address_1,
    address_2_meta.meta_value       as hotel_address_2,
    address_city_meta.meta_value    as hotel_address_city,
    address_zip_meta.meta_value     as hotel_address_zip,
    address_country_meta.meta_value as hotel_address_country,

    COUNT(review_meta.meta_value)   as review_total,
    AVG(review_meta.meta_value)     as review_avg,

    geo_lat_meta.meta_value         as hotel_geo_lat,
    geo_lng_meta.meta_value         as hotel_geo_lng,
    coverImage_meta.meta_value      as hotel_coverImage,
    phone_meta.meta_value           as hotel_phone,
    cheapest.*

FROM wp_users as hotel
         INNER JOIN wp_usermeta as address_1_meta       ON address_1_meta.user_id       = hotel.ID AND address_1_meta.meta_key       = 'address_1'
         INNER JOIN wp_usermeta as address_2_meta       ON address_2_meta.user_id       = hotel.ID AND address_2_meta.meta_key       = 'address_2'
         INNER JOIN wp_usermeta as address_city_meta    ON address_city_meta.user_id    = hotel.ID AND address_city_meta.meta_key    = 'address_city'
         INNER JOIN wp_usermeta as address_zip_meta     ON address_zip_meta.user_id     = hotel.ID AND address_zip_meta.meta_key     = 'address_zip'
         INNER JOIN wp_usermeta as address_country_meta ON address_country_meta.user_id = hotel.ID AND address_country_meta.meta_key = 'address_country'
         INNER JOIN wp_usermeta as geo_lat_meta         ON geo_lat_meta.user_id         = hotel.ID AND geo_lat_meta.meta_key         = 'geo_lat'
         INNER JOIN wp_usermeta as geo_lng_meta         ON geo_lng_meta.user_id         = hotel.ID AND geo_lng_meta.meta_key         = 'geo_lng'
         INNER JOIN wp_usermeta as coverImage_meta      ON coverImage_meta.user_id      = hotel.ID AND coverImage_meta.meta_key      = 'coverImage'
         INNER JOIN wp_usermeta as phone_meta           ON phone_meta.user_id           = hotel.ID AND phone_meta.meta_key           = 'phone'
         INNER JOIN (
    SELECT
        room.ID                   as cheapest_roomid,
        room.post_title           as cheapest_roomtitle,
        room.post_author          as cheapest_hotel_id,

        bathrooms_meta.meta_value as cheapest_bathrooms,
        bedrooms_meta.meta_value  as cheapest_bedrooms,
        img_meta.meta_value       as cheapest_img,
        surface_meta.meta_value   as cheapest_surface,
        type_meta.meta_value      as cheapest_type,
        MIN(CAST(price_meta.meta_value AS DECIMAL(10, 6)))     as cheapest_price

    FROM wp_posts as room
             INNER JOIN wp_postmeta as bathrooms_meta ON bathrooms_meta.post_id = room.ID AND bathrooms_meta.meta_key = 'bathrooms_count' AND CAST(bathrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bathrooms
             INNER JOIN wp_postmeta as bedrooms_meta  ON bedrooms_meta.post_id  = room.ID AND bedrooms_meta.meta_key  = 'bedrooms_count'  AND CAST(bedrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bedrooms
             INNER JOIN wp_postmeta as img_meta       ON img_meta.post_id       = room.ID AND img_meta.meta_key       = 'coverImage'
             INNER JOIN wp_postmeta as surface_meta   ON surface_meta.post_id   = room.ID AND surface_meta.meta_key   = 'surface' AND CAST(surface_meta.meta_value AS DECIMAL(10, 6)) >= :min_surface AND CAST(surface_meta.meta_value AS DECIMAL(10, 6)) < :max_surface
             INNER JOIN wp_postmeta as type_meta      ON type_meta.post_id      = room.ID AND type_meta.meta_key      = 'type' AND type_meta.meta_value IN :types
             INNER JOIN wp_postmeta as price_meta     ON price_meta.post_id     = room.ID AND price_meta.meta_key     = 'price' AND CAST(price_meta.meta_value AS DECIMAL(10, 6)) >= :min_price AND CAST(price_meta.meta_value AS DECIMAL(10, 6)) < :max_price

    WHERE
        room.post_type = 'room'


    GROUP BY room.post_author
) cheapest                                     ON cheapest.cheapest_hotel_id = hotel.ID
         INNER JOIN wp_posts    as rating_post          ON rating_post.post_author = hotel.ID     AND rating_post.post_type = 'review'
         INNER JOIN wp_postmeta as review_meta          ON review_meta.post_id = rating_post.ID   AND review_meta.meta_key  = 'rating'

WHERE
        (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(geo_lat_meta.meta_value AS DECIMAL(10, 6))))
            * COS(RADIANS(:latitude_from))
            * COS(RADIANS(CAST(geo_lng_meta.meta_value AS DECIMAL(10, 6)) - :longitude_from))
            + SIN(RADIANS(CAST(geo_lat_meta.meta_value AS DECIMAL(10, 6))))
            * SIN(RADIANS(:latitude_from)))))) <= :distance

GROUP BY hotel.ID;
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`

**Requête SQL d'ajout des indexes**

```sql
ALTER TABLE `wp_postmeta` ADD INDEX(`post_id`);
ALTER TABLE `wp_usermeta` ADD INDEX(`user_id`);
ALTER TABLE `wp_posts` ADD INDEX(`post_author`);
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | 1.7s        | 0.95s        |
| `OneRequestService`            | 1.37s       | 0.5s         |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | 1.4         | 0.5s         |
| `ReworkedHotelService`         | 1.20s       | 0.4          |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
CREATE TABLE `hotels` (
    `id` bigint(255) UNSIGNED NOT NULL,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `address_1` varchar(255) NOT NULL,
    `address_2` varchar(255) NOT NULL,
    `address_city` varchar(255) NOT NULL,
    `address_zipcode` varchar(255) NOT NULL,
    `address_country` varchar(100) NOT NULL,
    `geo_lat` float NOT NULL,
    `geo_lng` float NOT NULL,
    `phone` varchar(20) NOT NULL,
    `image_url` longtext NOT NULL
) ENGINE=InnoDB;
ALTER TABLE `hotels`
    ADD PRIMARY KEY (`id`);
```

```SQL
INSERT INTO hotels (
    SELECT
        hotel.ID                        as id,
        hotel.display_name              as name,
        hotel.user_email                as email,
        address_1_meta.meta_value       as address_1,
        address_2_meta.meta_value       as address_2,
        address_city_meta.meta_value    as address_city,
        address_zip_meta.meta_value     as address_zip,
        address_country_meta.meta_value as hotel_address_country,

        geo_lat_meta.meta_value         as geo_lat,
        geo_lng_meta.meta_value         as geo_lng,
        phone_meta.meta_value           as phone,
        coverImage_meta.meta_value      as image_url

    FROM wp_users as hotel
             INNER JOIN wp_usermeta as address_1_meta       ON address_1_meta.user_id       = hotel.ID AND address_1_meta.meta_key       = 'address_1'
             INNER JOIN wp_usermeta as address_2_meta       ON address_2_meta.user_id       = hotel.ID AND address_2_meta.meta_key       = 'address_2'
             INNER JOIN wp_usermeta as address_city_meta    ON address_city_meta.user_id    = hotel.ID AND address_city_meta.meta_key    = 'address_city'
             INNER JOIN wp_usermeta as address_zip_meta     ON address_zip_meta.user_id     = hotel.ID AND address_zip_meta.meta_key     = 'address_zip'
             INNER JOIN wp_usermeta as address_country_meta ON address_country_meta.user_id = hotel.ID AND address_country_meta.meta_key = 'address_country'
             INNER JOIN wp_usermeta as geo_lat_meta         ON geo_lat_meta.user_id         = hotel.ID AND geo_lat_meta.meta_key         = 'geo_lat'
             INNER JOIN wp_usermeta as geo_lng_meta         ON geo_lng_meta.user_id         = hotel.ID AND geo_lng_meta.meta_key         = 'geo_lng'
             INNER JOIN wp_usermeta as coverImage_meta      ON coverImage_meta.user_id      = hotel.ID AND coverImage_meta.meta_key      = 'coverImage'
             INNER JOIN wp_usermeta as phone_meta           ON phone_meta.user_id           = hotel.ID AND phone_meta.meta_key           = 'phone'

    GROUP BY hotel.ID
);
```

### Table `rooms` (1 200 lignes)

```SQL
CREATE TABLE `rooms` (
    `id` bigint(255) NOT NULL,
    `id_hotel` bigint(255) UNSIGNED NOT NULL,
    `title` varchar(100) NOT NULL,
    `price` float NOT NULL,
    `image` varchar(400) NOT NULL,
    `bedrooms` int(10) UNSIGNED NOT NULL,
    `bathrooms` int(10) UNSIGNED NOT NULL,
    `surface` FLOAT UNSIGNED NOT NULL,
    `type` varchar(100) NOT NULL
) ENGINE=InnoDB;
ALTER TABLE `rooms`
    ADD PRIMARY KEY (`id`),
    ADD KEY `id_hotel` (`id_hotel`),
    ADD CONSTRAINT `fk_rooms_hotel` FOREIGN KEY (`id_hotel`) REFERENCES `hotels` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
```

```SQL
INSERT INTO rooms(
    SELECT
        room.ID                   as id,
        room.post_author          as hotel_id,
        room.post_title           as title,
        price_meta.meta_value     as price,
        img_meta.meta_value       as img,
        bedrooms_meta.meta_value  as bedrooms,
        bathrooms_meta.meta_value as bathrooms,
        surface_meta.meta_value   as surface,
        type_meta.meta_value      as type

    FROM wp_posts as room
             INNER JOIN wp_postmeta as bathrooms_meta ON bathrooms_meta.post_id = room.ID AND bathrooms_meta.meta_key = 'bathrooms_count'
             INNER JOIN wp_postmeta as bedrooms_meta  ON bedrooms_meta.post_id = room.ID  AND bedrooms_meta.meta_key  = 'bedrooms_count'
             INNER JOIN wp_postmeta as img_meta       ON img_meta.post_id = room.ID       AND img_meta.meta_key       = 'coverImage'
             INNER JOIN wp_postmeta as surface_meta   ON surface_meta.post_id = room.ID   AND surface_meta.meta_key   = 'surface'
             INNER JOIN wp_postmeta as type_meta      ON type_meta.post_id = room.ID      AND type_meta.meta_key      = 'type'
             INNER JOIN wp_postmeta as price_meta     ON price_meta.post_id = room.ID     AND price_meta.meta_key     = 'price'

    WHERE
        room.post_type          = 'room'
    GROUP BY room.ID
);
```

### Table `reviews` (19 700 lignes)

```SQL
CREATE TABLE `reviews` (
                           `id` bigint(255) UNSIGNED NOT NULL,
                           `id_hotel` bigint(255) UNSIGNED NOT NULL,
                           `review` int(8) UNSIGNED NOT NULL
) ENGINE=InnoDB;
ALTER TABLE `reviews`
    ADD PRIMARY KEY (`id`),
    ADD KEY `id_hotel` (`id_hotel`),
    MODIFY `id` bigint(255) UNSIGNED NOT NULL AUTO_INCREMENT,
    ADD CONSTRAINT `fk_reviews_hotel` FOREIGN KEY (`id_hotel`) REFERENCES `hotels` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
```

```SQL
INSERT INTO reviews(
    SELECT
        0                          as id,
        hotel.ID                   as hotel_id,
        review_meta.meta_value     as review

    FROM wp_users as hotel
             INNER JOIN wp_posts    as rating_post          ON rating_post.post_author = hotel.ID     AND rating_post.post_type = 'review'
             INNER JOIN wp_postmeta as review_meta          ON review_meta.post_id = rating_post.ID   AND review_meta.meta_key  = 'rating'
);
```




## Question 9 : Utilisation d'une API pour les reviews

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `Avant API`                    | 0.41s       | 0.18s        |
| `Après ajout API`              | 14.8s       | 0.25s        |



## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| 18.5sec    | 1sec       |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec   |
|-----------------------|-------|--------|
| Total des fichiers JS | 1.1MB | 248kB  |
| `lodash.js`           | 562kB | 98.5kB |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : 73.5kB
- **Après** : 14.8kB

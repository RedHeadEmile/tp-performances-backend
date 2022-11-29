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


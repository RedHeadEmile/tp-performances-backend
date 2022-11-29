<?php

namespace App\Services\Hotel;

use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\DBService;
use App\Services\Room\RoomService;

class OneRequestHotelService extends AbstractHotelService
{
    use SingletonTrait;

    protected function __construct () {
        parent::__construct( new RoomService() );
    }

    protected function convertEntityFromArray(array $args): ?HotelEntity
    {
        $timeId = Timers::getInstance()->startTimer('convertEntityFromArray');

        $hotel = (new HotelEntity())
            ->setId($args['hotel_id'])
            ->setName($args['hotel_name'])
            ->setAddress([
                'address_1' => $args['hotel_address_1'],
                'address_2' => $args['hotel_address_2'],
                'address_city' => $args['hotel_address_city'],
                'address_zip' => $args['hotel_address_zip'],
                'address_country' => $args['hotel_address_country'],
            ])
            ->setRatingCount($args['review_total'])
            ->setRating(round($args['review_avg']))
            ->setGeoLat($args['hotel_geo_lat'])
            ->setGeoLng($args['hotel_geo_lng'])
            ->setImageUrl($args['hotel_coverImage'])
            ->setPhone($args['hotel_phone'])
            ->setCheapestRoom(
                (new RoomEntity())
                    ->setId($args['cheapest_roomid'])
                    ->setTitle($args['cheapest_roomtitle'])
                    ->setBathRoomsCount($args['cheapest_bathrooms'])
                    ->setBedRoomsCount($args['cheapest_bedrooms'])
                    ->setCoverImageUrl($args['cheapest_img'])
                    ->setSurface($args['cheapest_surface'])
                    ->setPrice(round($args['cheapest_price']))
                    ->setType($args['cheapest_type'])
            );

        Timers::getInstance()->endTimer('convertEntityFromArray', $timeId);
        return $hotel;
    }

    protected function getDB(): \PDO
    {
        $timerId = Timers::getInstance()->startTimer('getDB');
        $db = DBService::getPDO();
        Timers::getInstance()->endTimer('getDB', $timerId);
        return $db;
    }

    protected function buildQuery(array $args): \PDOStatement
    {
      $bathroomsPredicate  = (($args['bathrooms'] ?? null)      !== null) ? ' AND CAST(bathrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bathrooms' : '';
      $bedroomsPredicate = (($args['bedrooms'] ?? null)         !== null) ? ' AND CAST(bedrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bedrooms' : '';

      $minPricePredicate   = (($args['price']['min'] ?? null)   !== null) ? ' AND CAST(price_meta.meta_value AS DECIMAL(10, 6)) >= :price_min' : '';
      $maxPricePredicate   = (($args['price']['max'] ?? null)   !== null) ? ' AND CAST(price_meta.meta_value AS DECIMAL(10, 6)) < :price_max' : '';

      $minSurfacePredicate = (($args['surface']['min'] ?? null) !== null) ? ' AND CAST(surface_meta.meta_value AS DECIMAL(10, 6)) >= :surface_min' : '';
      $maxSurfacePredicate = (($args['surface']['max'] ?? null) !== null) ? ' AND CAST(surface_meta.meta_value AS DECIMAL(10, 6)) < :surface_max' : '';

      $typePredicate       = '';
      if (count($args['types']) > 0) {
        $inClause = '(';
        foreach ($args['types'] as $k => $v)
          $inClause .= ($k > 0 ? ', ' : '') . ':type' . $k;
        $typePredicate .= ' AND type_meta.meta_value IN ' . $inClause . ') ';
      }

      $distanceClause = '';
      if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
        $distanceClause .= 'WHERE
                    (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(geo_lat_meta.meta_value AS DECIMAL(10, 6))))
                        * COS(RADIANS(:latitude_from))
                        * COS(RADIANS(CAST(geo_lng_meta.meta_value AS DECIMAL(10, 6)) - :longitude_from))
                        + SIN(RADIANS(CAST(geo_lat_meta.meta_value AS DECIMAL(10, 6))))
                        * SIN(RADIANS(:latitude_from)))))) <= :distance';
      }

        $timerId = Timers::getInstance()->startTimer('buildQuery');
        $sql = '
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
         INNER JOIN wp_usermeta as address_1_meta       ON address_1_meta.user_id       = hotel.ID AND address_1_meta.meta_key       = \'address_1\'
         INNER JOIN wp_usermeta as address_2_meta       ON address_2_meta.user_id       = hotel.ID AND address_2_meta.meta_key       = \'address_2\'
         INNER JOIN wp_usermeta as address_city_meta    ON address_city_meta.user_id    = hotel.ID AND address_city_meta.meta_key    = \'address_city\'
         INNER JOIN wp_usermeta as address_zip_meta     ON address_zip_meta.user_id     = hotel.ID AND address_zip_meta.meta_key     = \'address_zip\'
         INNER JOIN wp_usermeta as address_country_meta ON address_country_meta.user_id = hotel.ID AND address_country_meta.meta_key = \'address_country\'
         INNER JOIN wp_usermeta as geo_lat_meta         ON geo_lat_meta.user_id         = hotel.ID AND geo_lat_meta.meta_key         = \'geo_lat\'
         INNER JOIN wp_usermeta as geo_lng_meta         ON geo_lng_meta.user_id         = hotel.ID AND geo_lng_meta.meta_key         = \'geo_lng\'
         INNER JOIN wp_usermeta as coverImage_meta      ON coverImage_meta.user_id      = hotel.ID AND coverImage_meta.meta_key      = \'coverImage\'
         INNER JOIN wp_usermeta as phone_meta           ON phone_meta.user_id           = hotel.ID AND phone_meta.meta_key           = \'phone\'
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
             INNER JOIN wp_postmeta as bathrooms_meta ON bathrooms_meta.post_id = room.ID AND bathrooms_meta.meta_key = \'bathrooms_count\' ' . $bathroomsPredicate . '
             INNER JOIN wp_postmeta as bedrooms_meta  ON bedrooms_meta.post_id  = room.ID AND bedrooms_meta.meta_key  = \'bedrooms_count\' ' . $bedroomsPredicate . '
             INNER JOIN wp_postmeta as img_meta       ON img_meta.post_id       = room.ID AND img_meta.meta_key       = \'coverImage\'
             INNER JOIN wp_postmeta as surface_meta   ON surface_meta.post_id   = room.ID AND surface_meta.meta_key   = \'surface\' ' . $minSurfacePredicate . $maxSurfacePredicate . '
             INNER JOIN wp_postmeta as type_meta      ON type_meta.post_id      = room.ID AND type_meta.meta_key      = \'type\' ' . $typePredicate . '
             INNER JOIN wp_postmeta as price_meta     ON price_meta.post_id     = room.ID AND price_meta.meta_key     = \'price\' ' . $minPricePredicate . $maxPricePredicate . '

    WHERE
        room.post_type = \'room\'
        
    GROUP BY room.post_author
) cheapest                                              ON cheapest.cheapest_hotel_id = hotel.ID
         INNER JOIN wp_posts    as rating_post          ON rating_post.post_author = hotel.ID     AND rating_post.post_type = \'review\'
         INNER JOIN wp_postmeta as review_meta          ON review_meta.post_id = rating_post.ID   AND review_meta.meta_key  = \'rating\'
         ' . $distanceClause . '
    GROUP BY hotel.ID;
';

        $stmt = $this->getDB()->prepare($sql);
        Timers::getInstance()->endTimer('buildQuery', $timerId);
        return $stmt;
    }

    /**
     * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
     *
     * @param array{
     *   search: string | null,
     *   lat: string | null,
     *   lng: string | null,
     *   price: array{min:float | null, max: float | null},
     *   surface: array{min:int | null, max: int | null},
     *   bedrooms: int | null,
     *   bathrooms: int | null,
     *   types: string[]
     * } $args Une liste de paramètres pour filtrer les résultats
     *
     * @throws Exception
     * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
     */
    public function list(array $args = []): array
    {
        $timerId = Timers::getInstance()->startTimer('list');

        $results = [];
        $stmt = $this->buildQuery($args);

        $sqlArgs = [];
        //if (($args['search'] ?? null)         !== null) $sqlArgs['search']         = $args['search'];
        if (($args['lat'] ?? null)            !== null) $sqlArgs['latitude_from']  = $args['lat'];
        if (($args['lng'] ?? null)            !== null) $sqlArgs['longitude_from'] = $args['lng'];
        if (($args['distance'] ?? null)       !== null) $sqlArgs['distance']       = $args['distance'];
        if (($args['price']['min'] ?? null)   !== null) $sqlArgs['price_min']      = $args['price']['min'];
        if (($args['price']['max'] ?? null)   !== null) $sqlArgs['price_max']      = $args['price']['max'];
        if (($args['surface']['min'] ?? null) !== null) $sqlArgs['surface_min']    = $args['surface']['min'];
        if (($args['surface']['max'] ?? null) !== null) $sqlArgs['surface_max']    = $args['surface']['max'];
        if (($args['bedrooms'] ?? null)       !== null) $sqlArgs['min_bedrooms']   = $args['bedrooms'];
        if (($args['bathrooms'] ?? null)      !== null) $sqlArgs['min_bathrooms']  = $args['bathrooms'];

        foreach ($args['types'] as $k => $v)
            $sqlArgs['type' . $k] = $v;

        $stmt->execute($sqlArgs);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $hotel)
            $results[] = $this->convertEntityFromArray($hotel);

        Timers::getInstance()->endTimer('list', $timerId);
        return $results;
    }
}
<?php

namespace App\Services\Hotel;

use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Services\Review\APIReviewsService;
use App\Services\Review\CachedApiReviewsService;

class ReworkedHotelService extends OneRequestHotelService
{
    private readonly APIReviewsService $reviewService;
    protected function __construct()
    {
        parent::__construct();
        $this->reviewService = new CachedApiReviewsService('http://cheap-trusted-reviews.fake/');
    }

    protected function convertEntityFromArray(array $args): ?HotelEntity
    {
        $timeId = Timers::getInstance()->startTimer('convertEntityFromArray');

        $reviews = $this->reviewService->get($args['hotel_id']);

        $hotel = parent::convertEntityFromArray($args)
            ->setRatingCount($reviews['data']['count'])
            ->setRating(round($reviews['data']['rating']));

        Timers::getInstance()->endTimer('convertEntityFromArray', $timeId);
        return $hotel;
    }

    protected function buildQuery(array $args): \PDOStatement
    {
        $bathroomsPredicate  = (($args['bathRooms'] ?? null)      !== null) ? ' AND rooms.bathrooms >= :min_bathrooms' : '';
        $bedroomsPredicate   = (($args['rooms'] ?? null)         !== null) ? ' AND rooms.bedrooms >= :min_bedrooms' : '';

        $minPricePredicate   = (($args['price']['min'] ?? null)   !== null) ? ' AND rooms.price >= :price_min' : '';
        $maxPricePredicate   = (($args['price']['max'] ?? null)   !== null) ? ' AND rooms.price < :price_max' : '';

        $minSurfacePredicate = (($args['surface']['min'] ?? null) !== null) ? ' AND rooms.surface >= :surface_min' : '';
        $maxSurfacePredicate = (($args['surface']['max'] ?? null) !== null) ? ' AND rooms.surface < :surface_max' : '';

        $typePredicate       = '';
        if (count($args['types']) > 0) {
            $inClause = '(';
            foreach ($args['types'] as $k => $v)
                $inClause .= ($k > 0 ? ', ' : '') . ':type' . $k;
            $typePredicate .= ' AND rooms.type IN ' . $inClause . ') ';
        }

        $distanceClause = '';
        if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
            $distanceClause .= 'WHERE
                    (111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(hotels.geo_lat))
                        * COS(RADIANS(:latitude_from))
                        * COS(RADIANS(hotels.geo_lng - :longitude_from))
                        + SIN(RADIANS(hotels.geo_lat))
                        * SIN(RADIANS(:latitude_from)))))) <= :distance';
        }

        $timerId = Timers::getInstance()->startTimer('buildQuery');
        $sql = '
SELECT
    hotels.id              as hotel_id,
    hotels.name            as hotel_name,
    hotels.address_1       as hotel_address_1,
    hotels.address_2       as hotel_address_2,
    hotels.address_city    as hotel_address_city,
    hotels.address_zipcode as hotel_address_zip,
    hotels.address_country as hotel_address_country,
    hotels.geo_lat         as hotel_geo_lat,
    hotels.geo_lng         as hotel_geo_lng,
    hotels.image_url       as hotel_coverImage,
    hotels.phone           as hotel_phone,
    
    COUNT(DISTINCT reviews.id)      as review_total,
    AVG(reviews.review)    as review_avg,

    rooms.id               as cheapest_roomid,
    rooms.title            as cheapest_roomtitle,
    rooms.bathrooms        as cheapest_bathrooms,
    rooms.bedrooms         as cheapest_bedrooms,
    rooms.image            as cheapest_img,
    rooms.surface          as cheapest_surface,
    rooms.type             as cheapest_type,
    MIN(rooms.price)       as cheapest_price

FROM hotels
    INNER JOIN rooms ON rooms.id_hotel = hotels.id ' . $bathroomsPredicate . $bedroomsPredicate . $minPricePredicate . $maxPricePredicate . $typePredicate . $minSurfacePredicate . $maxSurfacePredicate . '
    INNER JOIN reviews ON reviews.id_hotel = hotels.id
' . $distanceClause . '
GROUP BY hotels.id;';

        $stmt = $this->getDB()->prepare($sql);
        Timers::getInstance()->endTimer('buildQuery', $timerId);
        return $stmt;
    }
}
<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\DBService;
use App\Services\Room\RoomService;
use Cassandra\Time;
use Exception;
use PDO;

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
  
  
  protected function __construct () {
    parent::__construct( new RoomService() );
  }
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
    return DBService::getPDO();
  }
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {
    $timerId = Timers::getInstance()->startTimer('meta');
    $db = $this->getDB();
    $stmt = $db->prepare( 'SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = ?' );
    $stmt->execute([$userId, $key]);
    
    $result = $stmt->fetch( PDO::FETCH_ASSOC );
    $output = $result !== false ? $result['meta_value'] : null;

    Timers::getInstance()->endTimer('meta', $timerId);
    return $output;
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {
    $timerId = Timers::getInstance()->startTimer('metas');
    $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
    ];

    Timers::getInstance()->endTimer('metas', $timerId);
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    $timerId = Timers::getInstance()->startTimer('reviews');
    // Récupère tous les avis d'un hotel
    $stmt = $this->getDB()->prepare( 'SELECT COUNT(meta_value) as c, AVG(meta_value) as a FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = \'rating\' AND post_type = \'review\' GROUP BY wp_posts.post_author;' );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetch( PDO::FETCH_ASSOC );
    
    $output = [
      'rating' => round( $reviews['a'] ),
      'count' => $reviews['c'],
    ];

    Timers::getInstance()->endTimer('reviews', $timerId);
    return $output;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    $timerId = Timers::getInstance()->startTimer('cheapest');

    $sql = '
SELECT
    room.ID                   as roomid,
		room.post_title           as roomtitle,

		bathrooms_meta.meta_value as bathrooms,
		bedrooms_meta.meta_value  as bedrooms,
		img_meta.meta_value       as img,
		surface_meta.meta_value   as surface,
		type_meta.meta_value      as type,
		price_meta.meta_value     as price

FROM wp_posts as room
		INNER JOIN wp_postmeta as bathrooms_meta ON bathrooms_meta.post_id = room.ID AND bathrooms_meta.meta_key = \'bathrooms_count\'
		INNER JOIN wp_postmeta as bedrooms_meta  ON bedrooms_meta.post_id = room.ID  AND bedrooms_meta.meta_key  = \'bedrooms_count\'
		INNER JOIN wp_postmeta as img_meta       ON img_meta.post_id = room.ID       AND img_meta.meta_key       = \'coverImage\'
		INNER JOIN wp_postmeta as surface_meta   ON surface_meta.post_id = room.ID   AND surface_meta.meta_key   = \'surface\'
		INNER JOIN wp_postmeta as type_meta      ON type_meta.post_id = room.ID      AND type_meta.meta_key      = \'type\'
		INNER JOIN wp_postmeta as price_meta     ON price_meta.post_id = room.ID     AND price_meta.meta_key     = \'price\'

WHERE
		room.post_author            = :hotelId
		AND room.post_type          = \'room\'';

    $whereClauses = [];
    $sqlArguments = ['hotelId' => $hotel->getId()];
    if ( isset( $args['surface']['min'] ) ) {
      $whereClauses[] = 'CAST(surface_meta.meta_value AS DECIMAL(10, 6)) >= :min_surface';
      $sqlArguments['min_surface'] = $args['surface']['min'];
    }

    if ( isset( $args['surface']['max'] ) ) {
      $whereClauses[] = 'CAST(surface_meta.meta_value AS DECIMAL(10, 6)) < :max_surface';
      $sqlArguments['max_surface'] = $args['surface']['max'];
    }

    if ( isset( $args['price']['min'] ) ) {
      $whereClauses[] = 'CAST(price_meta.meta_value AS DECIMAL(10, 6)) >= :min_price';
      $sqlArguments['min_price'] = $args['price']['min'];
    }

    if ( isset( $args['price']['max'] ) ) {
      $whereClauses[] = 'CAST(price_meta.meta_value AS DECIMAL(10, 6)) < :max_price';
      $sqlArguments['max_price'] = $args['price']['max'];
    }

    if ( isset( $args['rooms'] ) ) {
      $whereClauses[] = 'CAST(bedrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bedrooms';
      $sqlArguments['min_bedrooms'] = $args['rooms'];
    }

    if ( isset( $args['bathRooms'] ) ) {
      $whereClauses[] = 'CAST(bathrooms_meta.meta_value AS DECIMAL(10, 6)) >= :min_bathrooms';
      $sqlArguments['min_bathrooms'] = $args['bathRooms'];
    }

    if ( isset( $args['types'] ) && ! empty( $args['types'] ) ) {
      $whereClause = 'type_meta.meta_value IN (';
      foreach ($args['types'] as $k => $v) {
        $sqlArguments['type' . $k] = $v;
        $whereClause .= ($k > 0 ? ', ' : '') . ':type' . $k;
      }
      $whereClauses[] = $whereClause . ')';
    }

    if (count($whereClauses) > 0)
      $sql .= ' AND ' . implode("\n AND ", $whereClauses);
    $sql .= ' GROUP BY room.ID ORDER BY CAST(price_meta.meta_value AS DECIMAL(10, 6)) ASC LIMIT 1;';

    $stmt = $this->getDB()->prepare($sql);
    $stmt->execute( $sqlArguments );

    $filteredRoom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($filteredRoom === false)
      throw new FilterException( "Aucune chambre ne correspond aux critères" );

    $cheapestRoom = new RoomEntity();
    $cheapestRoom->setId($filteredRoom['roomid']);
    $cheapestRoom->setTitle($filteredRoom['roomtitle']);
    $cheapestRoom->setBathRoomsCount($filteredRoom['bathrooms']);
    $cheapestRoom->setBedRoomsCount($filteredRoom['bedrooms']);
    $cheapestRoom->setCoverImageUrl($filteredRoom['img']);
    $cheapestRoom->setSurface($filteredRoom['surface']);
    $cheapestRoom->setPrice($filteredRoom['price']);
    $cheapestRoom->setType($filteredRoom['type']);

    Timers::getInstance()->endTimer('cheapest', $timerId);
    return $cheapestRoom;
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $timerId = Timers::getInstance()->startTimer('convert');
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }

    Timers::getInstance()->endTimer('convert', $timerId);
    return $hotel;
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
  public function list ( array $args = [] ) : array {
    $timerId = Timers::getInstance()->startTimer('list');
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }


    Timers::getInstance()->endTimer('list', $timerId);
    return $results;
  }
}
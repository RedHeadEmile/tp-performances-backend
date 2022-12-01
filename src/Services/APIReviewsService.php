<?php

namespace App\Services;

class APIReviewsService
{
    private readonly string $API_URL;

    public function __construct(string $API_URL)
    {
        $this->API_URL = $API_URL;
    }

    public function get(int $hotelId): array
    {
        return json_decode(file_get_contents($this->API_URL . '?hotel_id=' . $hotelId), true);
    }
}
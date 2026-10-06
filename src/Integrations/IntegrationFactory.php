<?php
declare(strict_types=1);

require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/IntegrationContract.php';
require_once __DIR__ . '/BookingComConnector.php';
require_once __DIR__ . '/HotelSpiderConnector.php';

final class IntegrationFactory
{
    public static function make(array $integration): IntegrationContract
    {
        return match ($integration['provider']) {
            'booking_com' => new BookingComConnector($integration),
            'hotel_spider' => new HotelSpiderConnector($integration),
            default => throw new RuntimeException('Unbekannter Provider: ' . $integration['provider']),
        };
    }
}

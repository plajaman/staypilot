<?php
declare(strict_types=1);

interface IntegrationContract
{
    public function test(): array;
    public function pullReservations(): array;
    public function pushAvailability(string $startDate, string $endDate): array;
}

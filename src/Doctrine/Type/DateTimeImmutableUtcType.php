<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Remplace le type « datetime_immutable » : les dates sont écrites et relues en UTC,
 * quel que soit le fuseau horaire de PHP (Europe/Brussels dans le conteneur, inconnu sur le VPS).
 *
 * Sans cela, une date enregistrée en UTC était relue comme une heure de Bruxelles : décalage
 * d'une à deux heures sur l'expiration des réservations et les dates renvoyées par l'API.
 * L'affichage convertit ensuite en heure de Bruxelles (EasyAdmin, e-mails, React).
 */
final class DateTimeImmutableUtcType extends DateTimeImmutableType
{
    private static ?\DateTimeZone $utc = null;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = $value->setTimezone(self::utc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $date = \DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), (string) $value, self::utc());
        if (false !== $date) {
            return $date;
        }

        try {
            return new \DateTimeImmutable((string) $value, self::utc());
        } catch (\Exception $e) {
            throw InvalidFormat::new((string) $value, static::class, $platform->getDateTimeFormatString(), $e);
        }
    }

    private static function utc(): \DateTimeZone
    {
        return self::$utc ??= new \DateTimeZone('UTC');
    }
}

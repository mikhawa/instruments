<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ZoneLivraison;
use App\Repository\ZoneLivraisonRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PaysDansUneSeuleZoneValidator extends ConstraintValidator
{
    public function __construct(private readonly ZoneLivraisonRepository $zoneLivraisonRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PaysDansUneSeuleZone) {
            throw new UnexpectedTypeException($constraint, PaysDansUneSeuleZone::class);
        }
        if (!$value instanceof ZoneLivraison) {
            return;
        }

        foreach ($value->getPays() as $pays) {
            $autre = $this->zoneLivraisonRepository->trouverAutreZonePour($pays, $value);
            if (null !== $autre) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ pays }}', $pays)
                    ->setParameter('{{ zone }}', (string) $autre->getNom())
                    ->atPath('pays')
                    ->addViolation();
            }
        }
    }
}

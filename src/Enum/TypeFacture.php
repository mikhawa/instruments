<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeFacture: string
{
    case Facture = 'facture';
    /** Avoir : tout remboursement d'une somme facturée (une facture émise n'est jamais modifiée) */
    case NoteCredit = 'note_credit';

    /**
     * Série de numérotation, continue par année : F2026-000001, NC2026-000001.
     */
    public function serie(): string
    {
        return match ($this) {
            self::Facture => 'F',
            self::NoteCredit => 'NC',
        };
    }
}

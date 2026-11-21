<?php

namespace App\Enums;

/**
 * What the unstitched pack contains. Which ones apply depends on ProductGender.
 */
enum PieceType: string
{
    case FullSuit = 'full_suit';
    case KameezOnly = 'kameez_only';
    case WaistcoatFabric = 'waistcoat';
    case TwoPiece = '2_piece';
    case ThreePiece = '3_piece';
    case ShirtOnly = 'shirt_only';
    case ShalwarOnly = 'shalwar_only';
    case DupattaOnly = 'dupatta_only';

    public function label(): string
    {
        return match ($this) {
            self::FullSuit => __('Full suit (kameez + shalwar)'),
            self::KameezOnly => __('Kameez / kurta only'),
            self::WaistcoatFabric => __('Waistcoat fabric'),
            self::TwoPiece => __('2 piece (shirt + trouser)'),
            self::ThreePiece => __('3 piece (shirt + trouser + dupatta)'),
            self::ShirtOnly => __('Shirt only'),
            self::ShalwarOnly => __('Shalwar / trouser only'),
            self::DupattaOnly => __('Dupatta only'),
        };
    }

    public function fitsGender(?ProductGender $gender): bool
    {
        return $gender === null || in_array($this, $gender->pieceTypes(), true);
    }
}

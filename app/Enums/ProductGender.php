<?php

namespace App\Enums;

/**
 * Who the cloth is for. It decides which piece types make sense: a gents suit is one full suit, ladies suits come in 2 or 3 pieces.
 */
enum ProductGender: string
{
    case Gents = 'gents';
    case Ladies = 'ladies';
    case Kids = 'kids';

    public function label(): string
    {
        return __(ucfirst($this->value));
    }

    /**
     * @return list<PieceType>
     */
    public function pieceTypes(): array
    {
        return match ($this) {
            self::Gents => [PieceType::FullSuit, PieceType::KameezOnly, PieceType::ShalwarOnly, PieceType::WaistcoatFabric],
            self::Ladies => [PieceType::TwoPiece, PieceType::ThreePiece, PieceType::ShirtOnly, PieceType::ShalwarOnly, PieceType::DupattaOnly],
            self::Kids => [PieceType::FullSuit, PieceType::ShirtOnly],
        };
    }
}

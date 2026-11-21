<?php

namespace App\Enums;

/**
 * How a family member is related to the customer account holder.
 */
enum FamilyRelation: string
{
    case Self = 'self';
    case Father = 'father';
    case Mother = 'mother';
    case Husband = 'husband';
    case Wife = 'wife';
    case Son = 'son';
    case Daughter = 'daughter';
    case Brother = 'brother';
    case Sister = 'sister';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Self => __('Self'),
            self::Father => __('Father'),
            self::Mother => __('Mother'),
            self::Husband => __('Husband'),
            self::Wife => __('Wife'),
            self::Son => __('Son'),
            self::Daughter => __('Daughter'),
            self::Brother => __('Brother'),
            self::Sister => __('Sister'),
            self::Other => __('Other relative'),
        };
    }

    /**
     * The measurement form that usually fits this person.
     */
    public function defaultTemplate(): MeasurementTemplate
    {
        return match ($this) {
            self::Mother, self::Wife, self::Daughter, self::Sister => MeasurementTemplate::Ladies,
            default => MeasurementTemplate::Gents,
        };
    }

    /**
     * Relations that can be chosen for a family member (Self is the account holder).
     *
     * @return list<self>
     */
    public static function familyMembers(): array
    {
        return array_values(array_filter(self::cases(), fn (self $relation) => $relation !== self::Self));
    }
}

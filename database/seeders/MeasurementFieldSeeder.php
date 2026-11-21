<?php

namespace Database\Seeders;

use App\Enums\MeasurementTemplate;
use App\Models\MeasurementField;
use Illuminate\Database\Seeder;

/**
 * Default naap formats (MSR02, MSR03, MSR10). Safe to re-run: existing fields, including renamed ones, are left alone.
 */
class MeasurementFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fitting = ['Loose', 'Regular', 'Slim'];
        $daman = ['Round', 'Straight'];

        $templates = [
            MeasurementTemplate::Gents->value => [
                'body' => [
                    'length' => 'Kameez length', 'shoulder' => 'Shoulder', 'chest' => 'Chest', 'waist' => 'Waist', 'hip' => 'Hip',
                    'sleeve_length' => 'Sleeve length', 'sleeve_opening' => 'Sleeve opening', 'armhole' => 'Armhole', 'collar' => 'Neck / collar',
                    'daman_width' => 'Daman (hem) width', 'trouser_length' => 'Shalwar / trouser length', 'pancha_width' => 'Shalwar / pancha width',
                    'thigh' => 'Thigh', 'knee' => 'Knee', 'bottom' => 'Ankle / bottom opening',
                ],
                'style' => [
                    'collar_type' => ['Collar type', ['Collar', 'Round', 'Band', 'V neck']],
                    'cuff_type' => ['Cuff type', ['Simple cuff', 'Round cuff', 'Square cuff', 'No cuff']],
                    'front_pocket' => ['Front pocket', ['None', 'One', 'Two']],
                    'side_pockets' => ['Side pockets', ['None', 'One', 'Two']],
                    'daman_type' => ['Daman type', $daman],
                    'trouser_style' => ['Shalwar / trouser style', ['Shalwar', 'Trouser', 'Cigarette', 'Patiala']],
                    'fitting' => ['Fitting', $fitting],
                ],
            ],
            MeasurementTemplate::Ladies->value => [
                'body' => [
                    'length' => 'Shirt length', 'shoulder' => 'Shoulder', 'chest' => 'Bust / chest', 'waist' => 'Waist', 'hip' => 'Hip',
                    'sleeve_length' => 'Sleeve length', 'sleeve_opening' => 'Sleeve opening', 'armhole' => 'Armhole', 'neck_depth_front' => 'Front neck depth',
                    'neck_depth_back' => 'Back neck depth', 'daman_width' => 'Daman (hem) width', 'trouser_length' => 'Shalwar / trouser length',
                    'pancha_width' => 'Shalwar / pancha width', 'thigh' => 'Thigh', 'knee' => 'Knee', 'bottom' => 'Ankle / bottom opening',
                ],
                'style' => [
                    'neck_style' => ['Neck style', ['Round', 'V neck', 'Boat', 'Collar', 'Band', 'Square']],
                    'sleeve_style' => ['Sleeve style', ['Full', 'Three quarter', 'Half', 'Sleeveless']],
                    'daman_type' => ['Daman type', $daman],
                    'trouser_style' => ['Shalwar / trouser style', ['Shalwar', 'Trouser', 'Cigarette', 'Patiala', 'Palazzo']],
                    'fitting' => ['Fitting', $fitting],
                ],
            ],
            MeasurementTemplate::Kids->value => [
                'body' => [
                    'length' => 'Kameez length', 'shoulder' => 'Shoulder', 'chest' => 'Chest', 'waist' => 'Waist',
                    'sleeve_length' => 'Sleeve length', 'collar' => 'Neck / collar', 'trouser_length' => 'Shalwar / trouser length',
                    'bottom' => 'Bottom opening',
                ],
                'style' => [
                    'collar_type' => ['Collar type', ['Collar', 'Round', 'Band']],
                    'trouser_style' => ['Shalwar / trouser style', ['Shalwar', 'Trouser']],
                    'fitting' => ['Fitting', $fitting],
                ],
            ],
        ];

        foreach ($templates as $template => $groups) {
            $order = 0;

            foreach ($groups['body'] as $key => $label) {
                MeasurementField::query()->firstOrCreate(['template' => $template, 'key' => $key], [
                    'label' => $label, 'group' => MeasurementField::GROUP_BODY, 'type' => MeasurementField::TYPE_NUMBER, 'sort_order' => ++$order,
                ]);
            }

            foreach ($groups['style'] as $key => [$label, $options]) {
                MeasurementField::query()->firstOrCreate(['template' => $template, 'key' => $key], [
                    'label' => $label, 'group' => MeasurementField::GROUP_STYLE, 'type' => MeasurementField::TYPE_SELECT, 'options' => $options, 'sort_order' => ++$order,
                ]);
            }
        }
    }
}

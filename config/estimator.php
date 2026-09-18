<?php

/*
|--------------------------------------------------------------------------
| Estimator rate card
|--------------------------------------------------------------------------
|
| The single source of truth for estimator pricing and copy.
|
| These numbers used to live in two hand-maintained copies - the JS constants
| in resources/views/estimator.blade.php and nativeProjectTypes/nativeTiers/
| nativeAddons in the Android app's NativeEstimatorScreen.kt - and they had
| already drifted apart (the web offered a 5,000 sq m quick pick, the app
| stopped at 2,000; several tier and add-on descriptions differed word for
| word). A price change had to be made in two places or the two surfaces would
| quote a customer differently.
|
| The web view reads this directly. The Android app fetches it from
| GET /api/mobile/estimator-rates and falls back to a bundled copy when
| offline, so a rate change here reaches both without an app release.
|
| Presentation that is genuinely platform-specific - SVG icon paths, Tailwind
| classes, Compose alignments - deliberately stays in the view layer. Only
| pricing and customer-facing copy belong here.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Project types
    |--------------------------------------------------------------------------
    |
    | `rate` is in pesos per square metre and is multiplied by the area and the
    | selected tier multiplier to produce the base estimate.
    |
    */

    'project_types' => [
        'design' => [
            'label' => 'Garden Design',
            'booking_service_name' => 'Garden Design Consultation',
            'description' => 'Full landscape design, plant selection & installation.',
            'rate' => 50,
            'packages' => [
                'standard' => ['package_title' => 'Starter Garden', 'caption' => 'A practical garden using common plants, lawn, and simple edging.', 'examples' => ['Common shrubs and groundcover', 'Basic soil preparation', 'Simple edging and layout']],
                'premium' => ['package_title' => 'Enhanced Garden', 'caption' => 'A polished garden with mature planting, a refined path, stone edging, and lighting.', 'examples' => ['Mature plants and layered planting', 'Decorative stone and edging', 'Selected garden lighting']],
                'luxury' => ['package_title' => 'Signature Landscape', 'caption' => 'A bespoke landscape with specimen plants, custom stonework, lighting, and a water feature.', 'examples' => ['Rare or specimen plants', 'Custom hardscape and irrigation', 'Water feature or signature focal point']],
            ],
        ],
        'maintenance' => [
            'label' => 'Maintenance',
            'booking_service_name' => 'Routine Maintenance',
            'description' => 'Regular lawn care, pruning, weeding & cleanup.',
            'rate' => 10,
            'packages' => [
                'standard' => ['package_title' => 'Routine Garden Care', 'caption' => 'Essential recurring care that keeps lawns, beds, and paths tidy.', 'examples' => ['Lawn mowing and edging', 'Routine pruning and weeding', 'Green-waste cleanup']],
                'premium' => ['package_title' => 'Detailed Garden Care', 'caption' => 'More frequent, detailed upkeep for established gardens and feature planting.', 'examples' => ['Detailed pruning and shaping', 'Bed cultivation and mulch touch-ups', 'Plant-health and irrigation checks']],
                'luxury' => ['package_title' => 'Estate Care Program', 'caption' => 'High-touch maintenance for complex landscapes, specialty plants, and outdoor features.', 'examples' => ['Specialist specimen-plant care', 'Seasonal presentation planning', 'Priority feature and irrigation upkeep']],
            ],
        ],
        'hardscaping' => [
            'label' => 'Hardscaping',
            'booking_service_name' => 'Hardscaping Quote',
            'description' => 'Patios, walkways, retaining walls & stonework.',
            'rate' => 120,
            'packages' => [
                'standard' => ['package_title' => 'Essential Hardscape', 'caption' => 'A durable, straightforward paved area or path using practical finishes.', 'examples' => ['Base preparation and drainage allowance', 'Standard pavers or concrete finish', 'Simple borders and transitions']],
                'premium' => ['package_title' => 'Crafted Outdoor Space', 'caption' => 'A refined patio, path, or wall with upgraded materials and detailed finishing.', 'examples' => ['Premium pavers or natural-stone accents', 'Detailed borders and step transitions', 'Integrated drainage and selected lighting']],
                'luxury' => ['package_title' => 'Signature Stonework', 'caption' => 'Bespoke masonry and outdoor construction with premium stone and custom detailing.', 'examples' => ['Custom natural-stone layout', 'Built-in seating or feature walls', 'Architectural lighting and drainage detailing']],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality tiers
    |--------------------------------------------------------------------------
    |
    | `visual_index` is the panel this tier occupies in the three-across sprite
    | at public/images/tier-package-visuals.png. Each platform maps the index to
    | its own positioning (CSS background-position on the web, a Compose
    | TransformOrigin pivot on Android) - sharing the index rather than either
    | platform's coordinates keeps a sprite reorder from silently breaking one.
    |
    */

    'tiers' => [
        'standard' => [
            'label' => 'Standard',
            'multiplier' => 1.0,
            'description' => 'Budget-friendly materials with solid craftsmanship.',
            'package_title' => 'Starter Garden',
            'caption' => 'A practical garden using common plants, lawn, and simple edging.',
            'examples' => [
                'Common shrubs and groundcover',
                'Basic soil preparation',
                'Simple edging and layout',
            ],
            'visual_index' => 0,
        ],
        'premium' => [
            'label' => 'Premium',
            'multiplier' => 1.6,
            'description' => 'Higher-grade plants and materials with more visual detail.',
            'package_title' => 'Enhanced Garden',
            'caption' => 'A polished garden with mature planting, a refined path, stone edging, and lighting.',
            'examples' => [
                'Mature plants and layered planting',
                'Decorative stone and edging',
                'Selected garden lighting',
            ],
            'visual_index' => 1,
        ],
        'luxury' => [
            'label' => 'Luxury',
            'multiplier' => 2.4,
            'description' => 'Top-tier finishes, specimen plants, and bespoke design.',
            'package_title' => 'Signature Landscape',
            'caption' => 'A bespoke landscape with specimen plants, custom stonework, lighting, and a water feature.',
            'examples' => [
                'Rare or specimen plants',
                'Custom hardscape and irrigation',
                'Water feature or signature focal point',
            ],
            'visual_index' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional add-ons
    |--------------------------------------------------------------------------
    |
    | Flat peso amounts added on top of the base estimate.
    |
    */

    'addons' => [
        'irrigation' => [
            'label' => 'Irrigation System',
            'description' => 'Automated sprinkler & drip lines.',
            'amount' => 40000,
        ],
        'lighting' => [
            'label' => 'Outdoor Lighting',
            'description' => 'Path lights, spotlights & accent LEDs.',
            'amount' => 25000,
        ],
        'water' => [
            'label' => 'Water Feature',
            'description' => 'Custom pond, fountain or water wall.',
            'amount' => 60000,
        ],
        'pergola' => [
            'label' => 'Pergola / Gazebo',
            'description' => 'Shaded structure for outdoor living.',
            'amount' => 80000,
        ],
        'fence' => [
            'label' => 'Decorative Fencing',
            'description' => 'Bamboo, wood or metal boundary fencing.',
            'amount' => 20000,
        ],
        'soil' => [
            'label' => 'Soil Preparation & Mulch',
            'description' => 'Deep aeration, enriched topsoil & mulch.',
            'amount' => 15000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick-pick property sizes (square metres)
    |--------------------------------------------------------------------------
    */

    'quick_sizes' => [50, 100, 250, 500, 1000, 2000, 5000],

    /*
    |--------------------------------------------------------------------------
    | Displayed range
    |--------------------------------------------------------------------------
    |
    | The "typical range" shown under the headline figure, as multipliers of the
    | computed total. A site visit is still required for a real quotation.
    |
    */

    'range' => [
        'low' => 0.8,
        'high' => 1.25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'project_type' => 'design',
        'tier' => 'standard',
        'size' => 100,
    ],

];

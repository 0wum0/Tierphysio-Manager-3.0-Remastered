/**
 * Calibrated surface landmarks for the bundled 2026-10 models.
 * Normalized world coordinates: longest bounding-box axis = 2, centered at origin.
 * Dog faces +Z (left = +X); cat/horse face -Z (left = -X).
 * Points denote muscle REGIONS, not segmented muscle boundaries. The supplied
 * GLBs each contain a single textured mesh. Never project these radially at runtime.
 * Keep existing IDs stable for saved findings. See docs/anatomy-3d.md.
 */
export const MUSCLE_GROUPS = {
  "dog": [
    {
      "id": "dog_head",
      "label": "Kopfmuskulatur",
      "anatomical": "Musculi capitis",
      "region": "head",
      "side": "midline",
      "pos": [
        0.0,
        0.71271,
        0.72
      ]
    },
    {
      "id": "dog_jaw",
      "label": "Kaumuskulatur",
      "anatomical": "M. masseter / M. temporalis",
      "region": "head",
      "side": "midline",
      "pos": [
        0.0,
        0.45439,
        0.78
      ]
    },
    {
      "id": "dog_neck",
      "label": "Nackenmuskulatur",
      "anatomical": "M. splenius / M. semispinalis capitis",
      "region": "neck",
      "side": "midline",
      "pos": [
        0.0,
        0.53576,
        0.43
      ]
    },
    {
      "id": "dog_neck_ventral",
      "label": "Halsmuskulatur ventral",
      "anatomical": "M. sternocephalicus / Mm. infrahyoidei",
      "region": "neck",
      "side": "midline",
      "pos": [
        -0.0,
        0.26,
        0.66692
      ]
    },
    {
      "id": "dog_shoulder_l",
      "label": "Schultermuskulatur links",
      "anatomical": "M. deltoideus / M. infraspinatus",
      "region": "shoulder",
      "side": "left",
      "pos": [
        0.23822,
        0.15,
        0.36
      ]
    },
    {
      "id": "dog_shoulder_r",
      "label": "Schultermuskulatur rechts",
      "anatomical": "M. deltoideus / M. infraspinatus",
      "region": "shoulder",
      "side": "right",
      "pos": [
        -0.23221,
        0.15,
        0.36
      ]
    },
    {
      "id": "dog_chest",
      "label": "Brustmuskulatur",
      "anatomical": "Mm. pectorales",
      "region": "chest",
      "side": "midline",
      "pos": [
        0.0,
        -0.02,
        0.58228
      ]
    },
    {
      "id": "dog_thoracic",
      "label": "Rückenmuskulatur (BWS)",
      "anatomical": "M. longissimus thoracis",
      "region": "back",
      "side": "midline",
      "pos": [
        0.0,
        0.32015,
        -0.03
      ]
    },
    {
      "id": "dog_lumbar",
      "label": "Lendenmuskulatur",
      "anatomical": "M. longissimus lumborum / Mm. multifidi",
      "region": "lumbar",
      "side": "midline",
      "pos": [
        0.0,
        0.32698,
        -0.38
      ]
    },
    {
      "id": "dog_belly",
      "label": "Bauchmuskulatur",
      "anatomical": "M. rectus abdominis",
      "region": "abdomen",
      "side": "midline",
      "pos": [
        0.0,
        -0.15103,
        -0.0
      ]
    },
    {
      "id": "dog_hip_l",
      "label": "Hüftmuskulatur links",
      "anatomical": "M. gluteus medius",
      "region": "hip",
      "side": "left",
      "pos": [
        0.18014,
        0.18,
        -0.57
      ]
    },
    {
      "id": "dog_hip_r",
      "label": "Hüftmuskulatur rechts",
      "anatomical": "M. gluteus medius",
      "region": "hip",
      "side": "right",
      "pos": [
        -0.17859,
        0.18,
        -0.57
      ]
    },
    {
      "id": "dog_glute_l",
      "label": "Glutealmuskulatur links",
      "anatomical": "M. gluteus superficialis",
      "region": "gluteal",
      "side": "left",
      "pos": [
        0.15273,
        0.1,
        -0.68
      ]
    },
    {
      "id": "dog_glute_r",
      "label": "Glutealmuskulatur rechts",
      "anatomical": "M. gluteus superficialis",
      "region": "gluteal",
      "side": "right",
      "pos": [
        -0.15338,
        0.1,
        -0.68
      ]
    },
    {
      "id": "dog_fore_l",
      "label": "Vorderbeinmuskulatur links",
      "anatomical": "M. triceps brachii",
      "region": "forelimb",
      "side": "left",
      "pos": [
        0.25689,
        -0.1,
        0.33
      ]
    },
    {
      "id": "dog_fore_r",
      "label": "Vorderbeinmuskulatur rechts",
      "anatomical": "M. triceps brachii",
      "region": "forelimb",
      "side": "right",
      "pos": [
        -0.25189,
        -0.1,
        0.33
      ]
    },
    {
      "id": "dog_hind_l",
      "label": "Hinterbeinmuskulatur links",
      "anatomical": "M. biceps femoris",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        0.2684,
        -0.2,
        -0.62
      ]
    },
    {
      "id": "dog_hind_r",
      "label": "Hinterbeinmuskulatur rechts",
      "anatomical": "M. biceps femoris",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        -0.26799,
        -0.2,
        -0.62
      ]
    },
    {
      "id": "dog_carpus_l",
      "label": "Karpalgelenk links",
      "anatomical": "Regio carpalis",
      "region": "carpus",
      "side": "left",
      "pos": [
        0.21514,
        -0.61,
        0.38
      ]
    },
    {
      "id": "dog_carpus_r",
      "label": "Karpalgelenk rechts",
      "anatomical": "Regio carpalis",
      "region": "carpus",
      "side": "right",
      "pos": [
        -0.20998,
        -0.61,
        0.38
      ]
    },
    {
      "id": "dog_tarsus_l",
      "label": "Sprunggelenk links",
      "anatomical": "Regio tarsi",
      "region": "tarsus",
      "side": "left",
      "pos": [
        0.278,
        -0.51,
        -0.77
      ]
    },
    {
      "id": "dog_tarsus_r",
      "label": "Sprunggelenk rechts",
      "anatomical": "Regio tarsi",
      "region": "tarsus",
      "side": "right",
      "pos": [
        -0.27714,
        -0.51,
        -0.77
      ]
    },
    {
      "id": "dog_paw_fl",
      "label": "Pfote vorne links",
      "anatomical": "Regio manus",
      "region": "paw",
      "side": "left",
      "pos": [
        0.25167,
        -0.79,
        0.46
      ]
    },
    {
      "id": "dog_paw_fr",
      "label": "Pfote vorne rechts",
      "anatomical": "Regio manus",
      "region": "paw",
      "side": "right",
      "pos": [
        -0.24796,
        -0.79,
        0.46
      ]
    },
    {
      "id": "dog_paw_hl",
      "label": "Pfote hinten links",
      "anatomical": "Regio pedis",
      "region": "paw",
      "side": "left",
      "pos": [
        0.36043,
        -0.79,
        -0.7
      ]
    },
    {
      "id": "dog_paw_hr",
      "label": "Pfote hinten rechts",
      "anatomical": "Regio pedis",
      "region": "paw",
      "side": "right",
      "pos": [
        -0.36048,
        -0.79,
        -0.7
      ]
    },
    {
      "id": "dog_tail",
      "label": "Schwanzbasis",
      "anatomical": "Regio caudalis",
      "region": "tail",
      "side": "midline",
      "pos": [
        -0.0,
        0.06198,
        -0.8
      ]
    },
    {
      "id": "dog_masseter_l",
      "label": "Kaumuskel links",
      "anatomical": "M. masseter",
      "region": "head",
      "side": "left",
      "pos": [
        0.20686,
        0.55,
        0.69
      ]
    },
    {
      "id": "dog_masseter_r",
      "label": "Kaumuskel rechts",
      "anatomical": "M. masseter",
      "region": "head",
      "side": "right",
      "pos": [
        -0.06038,
        0.55,
        0.69
      ]
    },
    {
      "id": "dog_temporalis_l",
      "label": "Schläfenmuskel links",
      "anatomical": "M. temporalis",
      "region": "head",
      "side": "left",
      "pos": [
        0.15325,
        0.69,
        0.68
      ]
    },
    {
      "id": "dog_temporalis_r",
      "label": "Schläfenmuskel rechts",
      "anatomical": "M. temporalis",
      "region": "head",
      "side": "right",
      "pos": [
        -0.10673,
        0.69,
        0.68
      ]
    },
    {
      "id": "dog_splenius_l",
      "label": "Riemenmuskel links",
      "anatomical": "M. splenius",
      "region": "neck",
      "side": "left",
      "pos": [
        0.1614,
        0.45,
        0.47
      ]
    },
    {
      "id": "dog_splenius_r",
      "label": "Riemenmuskel rechts",
      "anatomical": "M. splenius",
      "region": "neck",
      "side": "right",
      "pos": [
        -0.11358,
        0.45,
        0.47
      ]
    },
    {
      "id": "dog_brachiocephalicus_l",
      "label": "Kopf-Armmuskel links",
      "anatomical": "M. brachiocephalicus",
      "region": "neck",
      "side": "left",
      "pos": [
        0.21479,
        0.24,
        0.49
      ]
    },
    {
      "id": "dog_brachiocephalicus_r",
      "label": "Kopf-Armmuskel rechts",
      "anatomical": "M. brachiocephalicus",
      "region": "neck",
      "side": "right",
      "pos": [
        -0.19954,
        0.24,
        0.49
      ]
    },
    {
      "id": "dog_trapezius_l",
      "label": "Kapuzenmuskel links",
      "anatomical": "M. trapezius",
      "region": "shoulder",
      "side": "left",
      "pos": [
        0.139,
        0.3,
        0.26
      ]
    },
    {
      "id": "dog_trapezius_r",
      "label": "Kapuzenmuskel rechts",
      "anatomical": "M. trapezius",
      "region": "shoulder",
      "side": "right",
      "pos": [
        -0.13357,
        0.3,
        0.26
      ]
    },
    {
      "id": "dog_latissimus_l",
      "label": "Breiter Rückenmuskel links",
      "anatomical": "M. latissimus dorsi",
      "region": "back",
      "side": "left",
      "pos": [
        0.18748,
        0.14,
        -0.0
      ]
    },
    {
      "id": "dog_latissimus_r",
      "label": "Breiter Rückenmuskel rechts",
      "anatomical": "M. latissimus dorsi",
      "region": "back",
      "side": "right",
      "pos": [
        -0.18358,
        0.14,
        0.0
      ]
    },
    {
      "id": "dog_obliquus_l",
      "label": "Äußerer schiefer Bauchmuskel links",
      "anatomical": "M. obliquus externus abdominis",
      "region": "abdomen",
      "side": "left",
      "pos": [
        0.04931,
        -0.02,
        -0.25
      ]
    },
    {
      "id": "dog_obliquus_r",
      "label": "Äußerer schiefer Bauchmuskel rechts",
      "anatomical": "M. obliquus externus abdominis",
      "region": "abdomen",
      "side": "right",
      "pos": [
        -0.0443,
        -0.02,
        -0.25
      ]
    },
    {
      "id": "dog_quadriceps_l",
      "label": "Vierköpfiger Oberschenkelmuskel links",
      "anatomical": "M. quadriceps femoris",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        0.26071,
        -0.11,
        -0.5
      ]
    },
    {
      "id": "dog_quadriceps_r",
      "label": "Vierköpfiger Oberschenkelmuskel rechts",
      "anatomical": "M. quadriceps femoris",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        -0.26034,
        -0.11,
        -0.5
      ]
    },
    {
      "id": "dog_semitendinosus_l",
      "label": "Halbsehnenmuskel links",
      "anatomical": "M. semitendinosus",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        0.21374,
        -0.15967,
        -0.67686
      ]
    },
    {
      "id": "dog_semitendinosus_r",
      "label": "Halbsehnenmuskel rechts",
      "anatomical": "M. semitendinosus",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        -0.21223,
        -0.15997,
        -0.67705
      ]
    },
    {
      "id": "dog_gastrocnemius_l",
      "label": "Zwillingswadenmuskel links",
      "anatomical": "M. gastrocnemius",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        0.23753,
        -0.32049,
        -0.69909
      ]
    },
    {
      "id": "dog_gastrocnemius_r",
      "label": "Zwillingswadenmuskel rechts",
      "anatomical": "M. gastrocnemius",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        -0.23549,
        -0.3199,
        -0.69946
      ]
    }
  ],
  "cat": [
    {
      "id": "cat_head",
      "label": "Kopfmuskulatur",
      "anatomical": "Musculi capitis",
      "region": "head",
      "side": "midline",
      "pos": [
        -0.0,
        0.66211,
        -0.82
      ]
    },
    {
      "id": "cat_jaw",
      "label": "Kaumuskulatur",
      "anatomical": "M. masseter / M. temporalis",
      "region": "head",
      "side": "midline",
      "pos": [
        -0.0,
        0.40394,
        -0.88
      ]
    },
    {
      "id": "cat_neck",
      "label": "Nackenmuskulatur",
      "anatomical": "M. splenius / M. semispinalis capitis",
      "region": "neck",
      "side": "midline",
      "pos": [
        0.0,
        0.62837,
        -0.66
      ]
    },
    {
      "id": "cat_neck_ventral",
      "label": "Halsmuskulatur ventral",
      "anatomical": "M. sternocephalicus / Mm. infrahyoidei",
      "region": "neck",
      "side": "midline",
      "pos": [
        0.0,
        0.25,
        -0.78512
      ]
    },
    {
      "id": "cat_shoulder_l",
      "label": "Schultermuskulatur links",
      "anatomical": "M. deltoideus / M. infraspinatus",
      "region": "shoulder",
      "side": "left",
      "pos": [
        -0.23182,
        0.14,
        -0.59
      ]
    },
    {
      "id": "cat_shoulder_r",
      "label": "Schultermuskulatur rechts",
      "anatomical": "M. deltoideus / M. infraspinatus",
      "region": "shoulder",
      "side": "right",
      "pos": [
        0.23177,
        0.14,
        -0.59
      ]
    },
    {
      "id": "cat_chest",
      "label": "Brustmuskulatur",
      "anatomical": "Mm. pectorales",
      "region": "chest",
      "side": "midline",
      "pos": [
        0.0,
        -0.05,
        -0.72019
      ]
    },
    {
      "id": "cat_thoracic",
      "label": "Rückenmuskulatur (BWS)",
      "anatomical": "M. longissimus thoracis",
      "region": "back",
      "side": "midline",
      "pos": [
        -0.0,
        0.25883,
        -0.3
      ]
    },
    {
      "id": "cat_lumbar",
      "label": "Lendenmuskulatur",
      "anatomical": "M. longissimus lumborum / Mm. multifidi",
      "region": "lumbar",
      "side": "midline",
      "pos": [
        0.0,
        0.23902,
        0.12
      ]
    },
    {
      "id": "cat_belly",
      "label": "Bauchmuskulatur",
      "anatomical": "M. rectus abdominis",
      "region": "abdomen",
      "side": "midline",
      "pos": [
        -0.0,
        -0.21931,
        -0.25
      ]
    },
    {
      "id": "cat_hip_l",
      "label": "Hüftmuskulatur links",
      "anatomical": "M. gluteus medius",
      "region": "hip",
      "side": "left",
      "pos": [
        -0.17515,
        0.12,
        0.3
      ]
    },
    {
      "id": "cat_hip_r",
      "label": "Hüftmuskulatur rechts",
      "anatomical": "M. gluteus medius",
      "region": "hip",
      "side": "right",
      "pos": [
        0.17484,
        0.12,
        0.3
      ]
    },
    {
      "id": "cat_glute_l",
      "label": "Glutealmuskulatur links",
      "anatomical": "M. gluteus superficialis",
      "region": "gluteal",
      "side": "left",
      "pos": [
        -0.15595,
        0.06,
        0.41
      ]
    },
    {
      "id": "cat_glute_r",
      "label": "Glutealmuskulatur rechts",
      "anatomical": "M. gluteus superficialis",
      "region": "gluteal",
      "side": "right",
      "pos": [
        0.1558,
        0.06,
        0.41
      ]
    },
    {
      "id": "cat_fore_l",
      "label": "Vorderbeinmuskulatur links",
      "anatomical": "M. triceps brachii",
      "region": "forelimb",
      "side": "left",
      "pos": [
        -0.1629,
        -0.2186,
        -0.59274
      ]
    },
    {
      "id": "cat_fore_r",
      "label": "Vorderbeinmuskulatur rechts",
      "anatomical": "M. triceps brachii",
      "region": "forelimb",
      "side": "right",
      "pos": [
        0.16245,
        -0.22015,
        -0.59231
      ]
    },
    {
      "id": "cat_hind_l",
      "label": "Hinterbeinmuskulatur links",
      "anatomical": "M. biceps femoris",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.21782,
        -0.23,
        0.35
      ]
    },
    {
      "id": "cat_hind_r",
      "label": "Hinterbeinmuskulatur rechts",
      "anatomical": "M. biceps femoris",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.21746,
        -0.23,
        0.35
      ]
    },
    {
      "id": "cat_paw_fl",
      "label": "Pfote vorne links",
      "anatomical": "Regio manus",
      "region": "paw",
      "side": "left",
      "pos": [
        -0.16253,
        -0.705,
        -0.66
      ]
    },
    {
      "id": "cat_paw_fr",
      "label": "Pfote vorne rechts",
      "anatomical": "Regio manus",
      "region": "paw",
      "side": "right",
      "pos": [
        0.16262,
        -0.705,
        -0.66
      ]
    },
    {
      "id": "cat_paw_hl",
      "label": "Pfote hinten links",
      "anatomical": "Regio pedis",
      "region": "paw",
      "side": "left",
      "pos": [
        -0.19349,
        -0.705,
        0.46
      ]
    },
    {
      "id": "cat_paw_hr",
      "label": "Pfote hinten rechts",
      "anatomical": "Regio pedis",
      "region": "paw",
      "side": "right",
      "pos": [
        0.19304,
        -0.705,
        0.46
      ]
    },
    {
      "id": "cat_tail_base",
      "label": "Schwanzbasis",
      "anatomical": "Regio caudalis",
      "region": "tail",
      "side": "midline",
      "pos": [
        0.0,
        0.11611,
        0.5
      ]
    },
    {
      "id": "cat_tail",
      "label": "Schwanzmuskulatur",
      "anatomical": "Mm. caudales",
      "region": "tail",
      "side": "midline",
      "pos": [
        -0.0,
        -0.27525,
        0.68
      ]
    },
    {
      "id": "cat_masseter_l",
      "label": "Kaumuskel links",
      "anatomical": "M. masseter",
      "region": "head",
      "side": "left",
      "pos": [
        -0.15738,
        0.51,
        -0.79
      ]
    },
    {
      "id": "cat_masseter_r",
      "label": "Kaumuskel rechts",
      "anatomical": "M. masseter",
      "region": "head",
      "side": "right",
      "pos": [
        0.15735,
        0.51,
        -0.79
      ]
    },
    {
      "id": "cat_temporalis_l",
      "label": "Schläfenmuskel links",
      "anatomical": "M. temporalis",
      "region": "head",
      "side": "left",
      "pos": [
        -0.12681,
        0.61,
        -0.79
      ]
    },
    {
      "id": "cat_temporalis_r",
      "label": "Schläfenmuskel rechts",
      "anatomical": "M. temporalis",
      "region": "head",
      "side": "right",
      "pos": [
        0.12676,
        0.61,
        -0.79
      ]
    },
    {
      "id": "cat_splenius_l",
      "label": "Riemenmuskel links",
      "anatomical": "M. splenius",
      "region": "neck",
      "side": "left",
      "pos": [
        -0.14188,
        0.38,
        -0.7
      ]
    },
    {
      "id": "cat_splenius_r",
      "label": "Riemenmuskel rechts",
      "anatomical": "M. splenius",
      "region": "neck",
      "side": "right",
      "pos": [
        0.14143,
        0.38,
        -0.7
      ]
    },
    {
      "id": "cat_brachiocephalicus_l",
      "label": "Kopf-Armmuskel links",
      "anatomical": "M. brachiocephalicus",
      "region": "neck",
      "side": "left",
      "pos": [
        -0.12005,
        0.25,
        -0.68
      ]
    },
    {
      "id": "cat_brachiocephalicus_r",
      "label": "Kopf-Armmuskel rechts",
      "anatomical": "M. brachiocephalicus",
      "region": "neck",
      "side": "right",
      "pos": [
        0.11905,
        0.25,
        -0.68
      ]
    },
    {
      "id": "cat_trapezius_l",
      "label": "Kapuzenmuskel links",
      "anatomical": "M. trapezius",
      "region": "shoulder",
      "side": "left",
      "pos": [
        -0.1695,
        0.27,
        -0.55
      ]
    },
    {
      "id": "cat_trapezius_r",
      "label": "Kapuzenmuskel rechts",
      "anatomical": "M. trapezius",
      "region": "shoulder",
      "side": "right",
      "pos": [
        0.16947,
        0.27,
        -0.55
      ]
    },
    {
      "id": "cat_latissimus_l",
      "label": "Breiter Rückenmuskel links",
      "anatomical": "M. latissimus dorsi",
      "region": "back",
      "side": "left",
      "pos": [
        -0.18942,
        0.12,
        -0.33
      ]
    },
    {
      "id": "cat_latissimus_r",
      "label": "Breiter Rückenmuskel rechts",
      "anatomical": "M. latissimus dorsi",
      "region": "back",
      "side": "right",
      "pos": [
        0.18927,
        0.12,
        -0.33
      ]
    },
    {
      "id": "cat_obliquus_l",
      "label": "Äußerer schiefer Bauchmuskel links",
      "anatomical": "M. obliquus externus abdominis",
      "region": "abdomen",
      "side": "left",
      "pos": [
        -0.12684,
        -0.07,
        -0.1
      ]
    },
    {
      "id": "cat_obliquus_r",
      "label": "Äußerer schiefer Bauchmuskel rechts",
      "anatomical": "M. obliquus externus abdominis",
      "region": "abdomen",
      "side": "right",
      "pos": [
        0.12671,
        -0.07,
        -0.1
      ]
    },
    {
      "id": "cat_quadriceps_l",
      "label": "Vierköpfiger Oberschenkelmuskel links",
      "anatomical": "M. quadriceps femoris",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.21059,
        -0.1,
        0.26
      ]
    },
    {
      "id": "cat_quadriceps_r",
      "label": "Vierköpfiger Oberschenkelmuskel rechts",
      "anatomical": "M. quadriceps femoris",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.21038,
        -0.1,
        0.26
      ]
    },
    {
      "id": "cat_semitendinosus_l",
      "label": "Halbsehnenmuskel links",
      "anatomical": "M. semitendinosus",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.1491,
        -0.09,
        0.47
      ]
    },
    {
      "id": "cat_semitendinosus_r",
      "label": "Halbsehnenmuskel rechts",
      "anatomical": "M. semitendinosus",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.14945,
        -0.09,
        0.47
      ]
    },
    {
      "id": "cat_gastrocnemius_l",
      "label": "Zwillingswadenmuskel links",
      "anatomical": "M. gastrocnemius",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.21943,
        -0.31,
        0.42
      ]
    },
    {
      "id": "cat_gastrocnemius_r",
      "label": "Zwillingswadenmuskel rechts",
      "anatomical": "M. gastrocnemius",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.21929,
        -0.31,
        0.42
      ]
    }
  ],
  "horse": [
    {
      "id": "horse_head",
      "label": "Kopfmuskulatur",
      "anatomical": "Musculi capitis",
      "region": "head",
      "side": "midline",
      "pos": [
        0.00885,
        0.56758,
        -0.79047
      ]
    },
    {
      "id": "horse_jaw",
      "label": "Kaumuskulatur",
      "anatomical": "M. masseter / M. temporalis",
      "region": "head",
      "side": "midline",
      "pos": [
        0.01874,
        0.55145,
        -0.80767
      ]
    },
    {
      "id": "horse_neck",
      "label": "Halsmuskulatur",
      "anatomical": "Mm. colli",
      "region": "neck",
      "side": "midline",
      "pos": [
        -0.12833,
        0.48,
        -0.53
      ]
    },
    {
      "id": "horse_neck_dorsal",
      "label": "Nackenmuskulatur",
      "anatomical": "M. splenius / M. semispinalis capitis",
      "region": "neck",
      "side": "midline",
      "pos": [
        0.0,
        0.77857,
        -0.57
      ]
    },
    {
      "id": "horse_shoulder_l",
      "label": "Schultermuskulatur links",
      "anatomical": "M. infraspinatus / M. supraspinatus",
      "region": "shoulder",
      "side": "left",
      "pos": [
        -0.28686,
        0.17,
        -0.43
      ]
    },
    {
      "id": "horse_shoulder_r",
      "label": "Schultermuskulatur rechts",
      "anatomical": "M. infraspinatus / M. supraspinatus",
      "region": "shoulder",
      "side": "right",
      "pos": [
        0.2141,
        0.17,
        -0.43
      ]
    },
    {
      "id": "horse_chest",
      "label": "Brustmuskulatur",
      "anatomical": "Mm. pectorales",
      "region": "chest",
      "side": "midline",
      "pos": [
        -0.0,
        -0.01,
        -0.53848
      ]
    },
    {
      "id": "horse_withers",
      "label": "Widerristregion",
      "anatomical": "Regio interscapularis",
      "region": "withers",
      "side": "midline",
      "pos": [
        0.0,
        0.56538,
        -0.31
      ]
    },
    {
      "id": "horse_thoracic",
      "label": "Rückenmuskulatur (Sattellage)",
      "anatomical": "M. longissimus thoracis",
      "region": "back",
      "side": "midline",
      "pos": [
        0.0,
        0.38138,
        -0.03
      ]
    },
    {
      "id": "horse_lumbar",
      "label": "Lendenmuskulatur",
      "anatomical": "M. longissimus lumborum / Mm. multifidi",
      "region": "lumbar",
      "side": "midline",
      "pos": [
        -0.0,
        0.39024,
        0.3
      ]
    },
    {
      "id": "horse_belly",
      "label": "Bauchmuskulatur",
      "anatomical": "M. rectus abdominis",
      "region": "abdomen",
      "side": "midline",
      "pos": [
        0.0,
        -0.22025,
        -0.12
      ]
    },
    {
      "id": "horse_hip_l",
      "label": "Hüftmuskulatur links",
      "anatomical": "M. tensor fasciae latae",
      "region": "hip",
      "side": "left",
      "pos": [
        -0.23859,
        0.15,
        0.45
      ]
    },
    {
      "id": "horse_hip_r",
      "label": "Hüftmuskulatur rechts",
      "anatomical": "M. tensor fasciae latae",
      "region": "hip",
      "side": "right",
      "pos": [
        0.16752,
        0.15,
        0.45
      ]
    },
    {
      "id": "horse_glute_l",
      "label": "Glutealmuskulatur links",
      "anatomical": "M. gluteus medius",
      "region": "gluteal",
      "side": "left",
      "pos": [
        -0.2992,
        0.26,
        0.59
      ]
    },
    {
      "id": "horse_glute_r",
      "label": "Glutealmuskulatur rechts",
      "anatomical": "M. gluteus medius",
      "region": "gluteal",
      "side": "right",
      "pos": [
        0.2256,
        0.26,
        0.59
      ]
    },
    {
      "id": "horse_thigh_l",
      "label": "Oberschenkelmuskulatur links",
      "anatomical": "M. biceps femoris",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.31974,
        -0.04,
        0.62
      ]
    },
    {
      "id": "horse_thigh_r",
      "label": "Oberschenkelmuskulatur rechts",
      "anatomical": "M. biceps femoris",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.24828,
        -0.04,
        0.62
      ]
    },
    {
      "id": "horse_fore_l",
      "label": "Vorderbeinmuskulatur links",
      "anatomical": "M. triceps brachii",
      "region": "forelimb",
      "side": "left",
      "pos": [
        -0.27507,
        -0.15,
        -0.34
      ]
    },
    {
      "id": "horse_fore_r",
      "label": "Vorderbeinmuskulatur rechts",
      "anatomical": "M. triceps brachii",
      "region": "forelimb",
      "side": "right",
      "pos": [
        0.19028,
        -0.15,
        -0.43
      ]
    },
    {
      "id": "horse_hind_l",
      "label": "Hinterbeinmuskulatur links",
      "anatomical": "M. gastrocnemius",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.24251,
        -0.29,
        0.86
      ]
    },
    {
      "id": "horse_hind_r",
      "label": "Hinterbeinmuskulatur rechts",
      "anatomical": "M. gastrocnemius",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.16164,
        -0.29,
        0.73
      ]
    },
    {
      "id": "horse_carpus_l",
      "label": "Karpalgelenkregion links",
      "anatomical": "Regio carpalis",
      "region": "carpus",
      "side": "left",
      "pos": [
        -0.24852,
        -0.45,
        -0.25
      ]
    },
    {
      "id": "horse_carpus_r",
      "label": "Karpalgelenkregion rechts",
      "anatomical": "Regio carpalis",
      "region": "carpus",
      "side": "right",
      "pos": [
        0.17299,
        -0.45,
        -0.36
      ]
    },
    {
      "id": "horse_tarsus_l",
      "label": "Sprunggelenkregion links",
      "anatomical": "Regio tarsi",
      "region": "tarsus",
      "side": "left",
      "pos": [
        -0.2776,
        -0.45,
        0.93
      ]
    },
    {
      "id": "horse_tarsus_r",
      "label": "Sprunggelenkregion rechts",
      "anatomical": "Regio tarsi",
      "region": "tarsus",
      "side": "right",
      "pos": [
        0.22026,
        -0.45,
        0.72
      ]
    },
    {
      "id": "horse_fetlock_fl",
      "label": "Fesselregion vorne links",
      "anatomical": "Regio metacarpalis distalis",
      "region": "fetlock",
      "side": "left",
      "pos": [
        -0.27373,
        -0.76,
        -0.2
      ]
    },
    {
      "id": "horse_fetlock_fr",
      "label": "Fesselregion vorne rechts",
      "anatomical": "Regio metacarpalis distalis",
      "region": "fetlock",
      "side": "right",
      "pos": [
        0.20012,
        -0.76,
        -0.37
      ]
    },
    {
      "id": "horse_fetlock_hl",
      "label": "Fesselregion hinten links",
      "anatomical": "Regio metatarsalis distalis",
      "region": "fetlock",
      "side": "left",
      "pos": [
        -0.2955,
        -0.76,
        0.96
      ]
    },
    {
      "id": "horse_fetlock_hr",
      "label": "Fesselregion hinten rechts",
      "anatomical": "Regio metatarsalis distalis",
      "region": "fetlock",
      "side": "right",
      "pos": [
        0.2264,
        -0.76,
        0.68
      ]
    },
    {
      "id": "horse_hoof_fl",
      "label": "Hufregion vorne links",
      "anatomical": "Regio ungulae",
      "region": "hoof",
      "side": "left",
      "pos": [
        -0.28495,
        -0.87,
        -0.24
      ]
    },
    {
      "id": "horse_hoof_fr",
      "label": "Hufregion vorne rechts",
      "anatomical": "Regio ungulae",
      "region": "hoof",
      "side": "right",
      "pos": [
        0.21291,
        -0.87,
        -0.43
      ]
    },
    {
      "id": "horse_hoof_hl",
      "label": "Hufregion hinten links",
      "anatomical": "Regio ungulae",
      "region": "hoof",
      "side": "left",
      "pos": [
        -0.30095,
        -0.87,
        0.92
      ]
    },
    {
      "id": "horse_hoof_hr",
      "label": "Hufregion hinten rechts",
      "anatomical": "Regio ungulae",
      "region": "hoof",
      "side": "right",
      "pos": [
        0.24211,
        -0.87,
        0.6
      ]
    },
    {
      "id": "horse_tail",
      "label": "Schweifansatz",
      "anatomical": "Regio caudae",
      "region": "tail",
      "side": "midline",
      "pos": [
        0.0,
        0.29178,
        0.88
      ]
    },
    {
      "id": "horse_masseter_l",
      "label": "Kaumuskel links",
      "anatomical": "M. masseter",
      "region": "head",
      "side": "left",
      "pos": [
        0.0039,
        0.6,
        -0.78
      ]
    },
    {
      "id": "horse_masseter_r",
      "label": "Kaumuskel rechts",
      "anatomical": "M. masseter",
      "region": "head",
      "side": "right",
      "pos": [
        0.25882,
        0.6,
        -0.78
      ]
    },
    {
      "id": "horse_temporalis_l",
      "label": "Schläfenmuskel links",
      "anatomical": "M. temporalis",
      "region": "head",
      "side": "left",
      "pos": [
        0.01383,
        0.76,
        -0.76
      ]
    },
    {
      "id": "horse_temporalis_r",
      "label": "Schläfenmuskel rechts",
      "anatomical": "M. temporalis",
      "region": "head",
      "side": "right",
      "pos": [
        0.22782,
        0.76,
        -0.76
      ]
    },
    {
      "id": "horse_splenius_l",
      "label": "Riemenmuskel links",
      "anatomical": "M. splenius",
      "region": "neck",
      "side": "left",
      "pos": [
        -0.12836,
        0.54,
        -0.56
      ]
    },
    {
      "id": "horse_splenius_r",
      "label": "Riemenmuskel rechts",
      "anatomical": "M. splenius",
      "region": "neck",
      "side": "right",
      "pos": [
        0.10062,
        0.54,
        -0.56
      ]
    },
    {
      "id": "horse_brachiocephalicus_l",
      "label": "Kopf-Armmuskel links",
      "anatomical": "M. brachiocephalicus",
      "region": "neck",
      "side": "left",
      "pos": [
        -0.14038,
        0.35,
        -0.54
      ]
    },
    {
      "id": "horse_brachiocephalicus_r",
      "label": "Kopf-Armmuskel rechts",
      "anatomical": "M. brachiocephalicus",
      "region": "neck",
      "side": "right",
      "pos": [
        0.09811,
        0.35,
        -0.54
      ]
    },
    {
      "id": "horse_trapezius_l",
      "label": "Kapuzenmuskel links",
      "anatomical": "M. trapezius",
      "region": "shoulder",
      "side": "left",
      "pos": [
        -0.17958,
        0.36,
        -0.33
      ]
    },
    {
      "id": "horse_trapezius_r",
      "label": "Kapuzenmuskel rechts",
      "anatomical": "M. trapezius",
      "region": "shoulder",
      "side": "right",
      "pos": [
        0.10626,
        0.36,
        -0.33
      ]
    },
    {
      "id": "horse_latissimus_l",
      "label": "Breiter Rückenmuskel links",
      "anatomical": "M. latissimus dorsi",
      "region": "back",
      "side": "left",
      "pos": [
        -0.26078,
        0.19,
        -0.05
      ]
    },
    {
      "id": "horse_latissimus_r",
      "label": "Breiter Rückenmuskel rechts",
      "anatomical": "M. latissimus dorsi",
      "region": "back",
      "side": "right",
      "pos": [
        0.18787,
        0.19,
        -0.05
      ]
    },
    {
      "id": "horse_obliquus_l",
      "label": "Äußerer schiefer Bauchmuskel links",
      "anatomical": "M. obliquus externus abdominis",
      "region": "abdomen",
      "side": "left",
      "pos": [
        -0.27346,
        -0.03,
        0.22
      ]
    },
    {
      "id": "horse_obliquus_r",
      "label": "Äußerer schiefer Bauchmuskel rechts",
      "anatomical": "M. obliquus externus abdominis",
      "region": "abdomen",
      "side": "right",
      "pos": [
        0.2008,
        -0.03,
        0.22
      ]
    },
    {
      "id": "horse_quadriceps_l",
      "label": "Vierköpfiger Oberschenkelmuskel links",
      "anatomical": "M. quadriceps femoris",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.31288,
        -0.1,
        0.65
      ]
    },
    {
      "id": "horse_quadriceps_r",
      "label": "Vierköpfiger Oberschenkelmuskel rechts",
      "anatomical": "M. quadriceps femoris",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.2064,
        -0.1,
        0.51
      ]
    },
    {
      "id": "horse_semitendinosus_l",
      "label": "Halbsehnenmuskel links",
      "anatomical": "M. semitendinosus",
      "region": "hindlimb",
      "side": "left",
      "pos": [
        -0.29104,
        0.0,
        0.77
      ]
    },
    {
      "id": "horse_semitendinosus_r",
      "label": "Halbsehnenmuskel rechts",
      "anatomical": "M. semitendinosus",
      "region": "hindlimb",
      "side": "right",
      "pos": [
        0.21247,
        -0.0,
        0.77
      ]
    }
  ]
};

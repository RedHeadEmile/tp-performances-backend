<?php
/**
 * @var string $title
 */
?><!doctype html>
<html lang="fr" class="lg:text-[16px] text-[14px]">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title><?= $title ?? "Default " ?></title>
  
  <!-- Tailwind -->
  <script src="/assets/scripts/tailwindcss.js?v=<?= VERSION ?>"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            "sky": {
              "50": "#E8F0FD",
              "100": "#CCDEFA",
              "200": "#9EC1F5",
              "300": "#6BA0EF",
              "400": "#3D83EA",
              "500": "#1766D9",
              "600": "#1351AF",
              "700": "#0E3C81",
              "800": "#092958",
              "900": "#041329"
            }
          }
        }
      }
    }
  </script>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@100;200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Icons -->
  <link rel="stylesheet" href="/assets/styles/iconoir.css?v=<?= VERSION ?>" />
  <link rel="stylesheet" href="/assets/styles/phosphor-icons.css?v=<?= VERSION ?>" />
  
  <!-- Leaflet -->
  <link rel="stylesheet" href="/assets/styles/leaflet.css?v=<?= VERSION ?>" />
  <script src="/assets/scripts/leaflet.js?v=<?= VERSION ?>"></script>
  
  <!-- Lodash -->
  <script src="/assets/scripts/lodash.js?v=<?= VERSION ?>"></script>
  
  <!-- Assets -->
  <link rel="stylesheet" href="/assets/styles/main.css?v=<?= VERSION ?>" />
  <style type="text/tailwindcss">
    @layer utilities {
      .btn {
        @apply rounded-xl px-4 py-3 bg-white hover:bg-slate-100 text-sky-500 text-lg transition-colors duration-300;
      }
      
      .btn.btn-primary {
        @apply rounded-xl px-4 py-3 bg-sky-500 hover:bg-sky-400 text-white text-lg;
      }
    }
  </style>
  <script src="/assets/scripts/map.js?v=<?= VERSION ?>" defer></script>
</head>
<body style="font-family: 'Barlow', sans-serif">

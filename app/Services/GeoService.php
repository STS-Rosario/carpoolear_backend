<?php

namespace STS\Services;

class GeoService
{
    private $paidRegions;

    public function __construct()
    {
        $buenosAires = [
            [-34.259825,-58.218597],
            [-34.282523,-58.883270],
            [-34.515947,-59.039825],
            [-35.023585,-58.460297],
            [-34.762259,-58.103241],
            [-34.259825,-58.218597]
        ];

        $rosario = [
            [-32.824045,-60.698163],
            [-32.838470,-60.813519],
            [-32.899602,-60.943295],
            [-33.081596,-60.926816],
            [-33.159807,-60.814206],
            [-33.163256,-60.674130],
            [-33.052825,-60.549161],
            [-33.001012,-60.608212],
            [-32.951474,-60.615079],
            [-32.939950,-60.626065],
            [-32.905367,-60.671384],
            [-32.870771,-60.672757],
            [-32.824045,-60.698163]
        ];

        $cordoba = [
            [-31.273659,-64.278703],
            [-31.328809,-64.358354],
            [-31.494651,-64.258791],
            [-31.498749,-64.089189],
            [-31.414989,-64.056917],
            [-31.346403,-64.061724],
            [-31.273659,-64.278703]
        ];

        $marDelPlata = [
            [-37.793679,-57.450653],
            [-37.856594,-57.762390],
            [-38.021221,-57.879120],
            [-38.229721,-57.693725],
            [-38.052588,-57.475372],
            [-37.793679,-57.450653]
        ];

        $laPlata = [
            [-34.787076,-57.990631],
            [-34.980838,-58.171906],
            [-35.084291,-58.023590],
            [-34.850211,-57.787384],
            [-34.787076,-57.990631]
        ];

        $this->paidRegions = [
            $buenosAires,
            $rosario,
            $cordoba,
            $marDelPlata,
            $laPlata
        ];
    }

    public function arePointsInPaidRegions(array $points): bool
    {
        foreach ($points as $point) {
            if (!$this->isPointInPolygons($point)) {
                return false;
            }
        }
        return true;
    }

    private function isPointInPolygons(array $point): bool
    {
        foreach ($this->paidRegions as $polygon) {
            if ($this->isPointInPolygon($polygon, $point)) {
                return true;
            }
        }
        return false;
    }

    private function isPointInPolygon(array $polygon, array $point): bool
    {
        $lat = $point[0];
        $lng = $point[1];
        $inside = false;
        $numPoints = count($polygon);

        for ($i = 0, $j = $numPoints - 1; $i < $numPoints; $j = $i++) {
            $lat_i = $polygon[$i][0];
            $lng_i = $polygon[$i][1];
            $lat_j = $polygon[$j][0];
            $lng_j = $polygon[$j][1];

            $intersect = (($lat_i > $lat) !== ($lat_j > $lat)) &&
                         ($lng < ($lng_j - $lng_i) * ($lat - $lat_i) / (($lat_j - $lat_i) ?: 1e-10) + $lng_i);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
} 
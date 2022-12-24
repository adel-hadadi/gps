<?php


namespace App\Http\Controllers;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class GpsController extends Controller
{
    const MAX_LOCATION_TIME = '-1 day';

    public function gps()
    {
        $vehicles = $this->getVehiclesByLatestService();
        Log::info(json_encode($vehicles));

        foreach ($vehicles as $vehicle) {
            $locations = $this->getVehicleLocations($vehicle->last_location_at, $vehicle->IMEI);

            if (!$locations)
                continue;

            foreach ($locations as $location) {
                DB::table('vehicleLocation')->insert([
                    'vehicle_id' => $vehicle->id,
                    'deviceID' => $vehicle->IMEI,
                    'distance' => 0,
                    'simNumber' => 0,
                    'altitude' => 0,
                    'lat' => $location['Latitude'],
                    'lng' => $location['Longitude'],
                    'speed' => $location['Speed'],
                    'bearing' => $location['Bearing'],
                    'time' => $location['FaTime'],
                    'timeSet' => time(),
                ]);
            }

            DB::table('vehicle')->where('id', $vehicle->id)->update(['last_location_at' => time()]);
        }
    }

    protected function getVehiclesByLatestService()
    {
        $lastWeak = strtotime('-7 day', time());

        return DB::table('service')->select(DB::raw('vehicle.id, vehicle.last_location_at, vehicle.IMEI'))
            ->join('vehicle', 'vehicle.porter', '=', 'service.porter')
            ->where('service.date', '>=', $lastWeak)
            ->orderBy('vehicle.last_location_at')
            ->get();
    }

    protected function getVehicleLocations($lastLocationTime, $IMEI)
    {
        $maxLocationTime = strtotime(self::MAX_LOCATION_TIME, time());

        if ($lastLocationTime < $maxLocationTime || is_null($lastLocationTime))
            $lastLocationTime = $maxLocationTime;

        $lastLocationTime = Jalalian::forge($lastLocationTime)->format('YmdHis');
        $toDate = Jalalian::forge(time())->format('YmdHis');

        $url = sprintf('%s/AvlData?FromDate=%s&ToDate=%s&IMEI=%s', env('GPS_LOCATIONS_URL', 'http://gps.pgtco.ir/MasfaAvlDataApi/api'), $lastLocationTime, $toDate, $IMEI);
        return json_decode(file_get_contents($url), true);
    }
}

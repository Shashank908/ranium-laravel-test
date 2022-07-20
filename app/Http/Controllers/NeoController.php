<?php

namespace App\Http\Controllers;

use Config;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class NeoController extends Controller
{
    private $fromdate;
    private $todate;

    public function getneobydate()
    {
        return view('datepickerpg');
    }
    
    public function getapidata(Request $request)
    {
        $this->fromdate = $request->fromDate;
        $this->todate = $request->toDate;
        $current_date = Carbon::now()->format('Y-m-d');
        if(($current_date < $this->todate))
        {
            return redirect()->back()->with('success', 'ToDate Should be lesser than or equal to current date!!!'); 
        } elseif (empty($this->fromdate) || empty($this->todate)) {
            return redirect()->back()->with('success', 'FromDate and ToDate both Should not be empty!!!');
        }
        
        $url = Config::get('apidata.api_url').$this->fromdate.'&end_date='.$this->todate.'&api_key='.Config::get('apidata.api_key');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        $neo_api_data = json_decode($output, true);
        if(isset($neo_api_data['code']) && $neo_api_data['code'] != 200)
        {
            $error_message = isset($neo_api_data['error_message']) ? $neo_api_data['error_message'] : 'Something Went Wrong!!!!';
            return redirect()->back()->with('success', $error_message);
        }
        $neo_data_by_date = [];
        $neo_by_array = [];
        $E = [];
        $neo_velocity_kmph = [];
        $neo_distance_km = [];
        $neo_diameter_km = [];
        $neo_count_by_date = [];
        $avg_of_each_ast = 0;
        if(isset($neo_api_data['near_earth_objects']))
        {
            foreach ($neo_api_data['near_earth_objects'] as $key => $value) {
                $neo_data_by_date[$key] = $value;
                foreach ($neo_data_by_date[$key] as $data_by_date) {
                    $neo_by_array[] = $data_by_date;
                }
            }
        }

        if(!empty($neo_by_array))
        {
            foreach ($neo_by_array as $neo) {
                $E[] = $neo;
    
                foreach ($neo['estimated_diameter'] as $estemetd_diameterkey => $value) {
                    if ($estemetd_diameterkey == 'kilometers') {
                        $avg_of_each_ast = $avg_of_each_ast + ($value['estimated_diameter_min'] + $value['estimated_diameter_max']) / 2;
                        $neo_diameter_km[] = $value;
                    }
                }
                foreach ($neo['close_approach_data'] as $specification) {
                    foreach ($specification['relative_velocity'] as $relative_velocitykey => $value) {
                        if ($relative_velocitykey == 'kilometers_per_hour') {
                            $neo_velocity_kmph[] = $value;
                        }
                    }
                    foreach ($specification['miss_distance'] as $miss_distancekey => $value) {
                        if ($miss_distancekey == 'kilometers') {
                            $neo_distance_km[] = $value;
                        }
                    }
                }
            }
        }

        $neo_data_by_date_arrkeys = array_keys($neo_data_by_date);

        foreach ($neo_data_by_date_arrkeys as $key => $value) {
            $neo_count_by_date[$value] = count($neo_data_by_date[$value]);
        }
        arsort($neo_velocity_kmph);
        $fastestAseroid = Arr::first($neo_velocity_kmph);
        $fastestAseroidkey = array_key_first($neo_velocity_kmph);
        $fastestAseroidId = isset($neo_by_array[$fastestAseroidkey]['id'])? $neo_by_array[$fastestAseroidkey]['id'] : '';
        asort($neo_distance_km);
        $closestAseroid = Arr::first($neo_distance_km);
        $closestAseroidkey = array_key_first($neo_velocity_kmph);
        $closestAseroidId = isset($neo_by_array[$closestAseroidkey]['id']) ? $neo_by_array[$closestAseroidkey]['id'] : '';

        $neo_count_by_date_arry_keys = array_keys($neo_count_by_date);
        $neo_count_by_date_arry_values = array_values($neo_count_by_date);
        $avg_of_each_ast = (count($neo_diameter_km) > 0) ? ($avg_of_each_ast / count($neo_diameter_km)) : 0;
        return view('barchart', compact('avg_of_each_ast', 'fastestAseroidId', 'fastestAseroid', 'closestAseroidId', 'closestAseroid', 'neo_count_by_date_arry_keys', 'neo_count_by_date_arry_values'));
    }
}

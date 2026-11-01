<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleCatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $foundation = ['dashboard', 'employees', 'organization', 'recruitment', 'onboarding-offboarding', 'attendance', 'leave', 'shifts-rosters', 'payroll', 'benefits', 'separation-settlement', 'performance', 'training', 'employee-self-service', 'manager-self-service'];
        $modules = collect(config('hrm.modules'))->map(function (array $module) use ($foundation) {
            $module['delivery'] = in_array($module['slug'], $foundation, true) ? 'Foundation slice' : 'Planned';

            return $module;
        });

        return response()->json(['modules' => $modules->values()]);
    }
}

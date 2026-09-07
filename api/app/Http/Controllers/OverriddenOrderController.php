<?php

namespace App\Http\Controllers;

use Fleetbase\FleetOps\Http\Controllers\Api\v1\OrderController as BaseOrderController;
use Illuminate\Http\Request;

class OverriddenOrderController extends BaseOrderController
{
    /**
     * Override order creation input mapping to support fallback booking note/instructions ingestion.
     *
     * @param Request $request
     * @return array
     */
    protected function orderCreateInputFromRequest(Request $request): array
    {
        $input = parent::orderCreateInputFromRequest($request);

        if (empty($input['notes'])) {
            $input['notes'] = $request->input('meta.notes') 
                ?? $request->input('meta.special_instructions') 
                ?? $request->input('special_instructions');
        }

        return $input;
    }

    /**
     * Override order update input mapping to support fallback booking note/instructions ingestion.
     *
     * @param Request $request
     * @return array
     */
    protected function orderUpdateInputFromRequest(Request $request): array
    {
        $input = parent::orderUpdateInputFromRequest($request);

        if (empty($input['notes'])) {
            $input['notes'] = $request->input('meta.notes') 
                ?? $request->input('meta.special_instructions') 
                ?? $request->input('special_instructions');
        }

        return $input;
    }
}

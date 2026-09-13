<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * Soft-deletes orphaned ad-hoc order places that have no active orders or organization references.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            UPDATE places p
            SET p.deleted_at = NOW()
            WHERE p.deleted_at IS NULL
              AND p.uuid NOT IN (
                SELECT DISTINCT pay.pickup_uuid 
                FROM orders o 
                JOIN payloads pay ON o.payload_uuid = pay.uuid 
                WHERE o.deleted_at IS NULL AND o.status NOT IN ('completed', 'canceled', 'expired') AND pay.pickup_uuid IS NOT NULL
                UNION
                SELECT DISTINCT pay.dropoff_uuid 
                FROM orders o 
                JOIN payloads pay ON o.payload_uuid = pay.uuid 
                WHERE o.deleted_at IS NULL AND o.status NOT IN ('completed', 'canceled', 'expired') AND pay.dropoff_uuid IS NOT NULL
                UNION
                SELECT DISTINCT pay.return_uuid 
                FROM orders o 
                JOIN payloads pay ON o.payload_uuid = pay.uuid 
                WHERE o.deleted_at IS NULL AND o.status NOT IN ('completed', 'canceled', 'expired') AND pay.return_uuid IS NOT NULL
                UNION
                SELECT DISTINCT wp.place_uuid
                FROM orders o
                JOIN payloads pay ON o.payload_uuid = pay.uuid
                JOIN waypoints wp ON wp.payload_uuid = pay.uuid
                WHERE o.deleted_at IS NULL AND o.status NOT IN ('completed', 'canceled', 'expired') AND wp.deleted_at IS NULL AND wp.place_uuid IS NOT NULL
              )
              AND p.owner_uuid IS NULL
              AND p.uuid NOT IN (SELECT place_uuid FROM companies WHERE place_uuid IS NOT NULL)
              AND p.uuid NOT IN (SELECT place_uuid FROM contacts WHERE place_uuid IS NOT NULL)
              AND p.uuid NOT IN (SELECT place_uuid FROM vendors WHERE place_uuid IS NOT NULL)
              AND p.uuid NOT IN (SELECT current_place_uuid FROM assets WHERE current_place_uuid IS NOT NULL)
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reversal is not automatic as restoring deleted_at without a timestamp snapshot could restore previously soft-deleted places.
    }
};

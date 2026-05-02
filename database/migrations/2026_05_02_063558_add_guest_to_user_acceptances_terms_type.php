<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE user_acceptances DROP CONSTRAINT IF EXISTS user_acceptances_terms_type_check');
        DB::statement("ALTER TABLE user_acceptances ADD CONSTRAINT user_acceptances_terms_type_check CHECK (((terms_type)::text = ANY ((ARRAY['donor'::character varying, 'recipient'::character varying, 'mutual'::character varying, 'guest'::character varying])::text[])))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_acceptances DROP CONSTRAINT IF EXISTS user_acceptances_terms_type_check');
        DB::statement("ALTER TABLE user_acceptances ADD CONSTRAINT user_acceptances_terms_type_check CHECK (((terms_type)::text = ANY ((ARRAY['donor'::character varying, 'recipient'::character varying, 'mutual'::character varying])::text[])))");
    }
};

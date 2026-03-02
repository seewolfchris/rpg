<?php

use App\Models\CampaignInvitation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->string('status', 20)->default(CampaignInvitation::STATUS_PENDING)->after('invited_by');
            $table->string('role', 20)->default(CampaignInvitation::ROLE_PLAYER)->after('status');
            $table->timestamp('responded_at')->nullable()->after('accepted_at');

            $table->index(['status', 'created_at']);
            $table->index(['role', 'created_at']);
        });

        DB::table('campaign_invitations')
            ->whereNotNull('accepted_at')
            ->update([
                'status' => CampaignInvitation::STATUS_ACCEPTED,
                'role' => CampaignInvitation::ROLE_PLAYER,
                'responded_at' => DB::raw('accepted_at'),
            ]);

        DB::table('campaign_invitations')
            ->whereNull('accepted_at')
            ->update([
                'status' => CampaignInvitation::STATUS_PENDING,
                'role' => CampaignInvitation::ROLE_PLAYER,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_invitations', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['role', 'created_at']);
            $table->dropColumn(['status', 'role', 'responded_at']);
        });
    }
};

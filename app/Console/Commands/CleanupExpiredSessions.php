<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SessionService;

class CleanupExpiredSessions extends Command
{
    protected $signature = 'session:cleanup';
    protected $description = 'Cleanup expired sessions and reset table status';

    protected $sessionService;

    public function __construct(SessionService $sessionService)
    {
        parent::__construct();
        $this->sessionService = $sessionService;
    }

    public function handle()
    {
        $this->info('Starting session cleanup...');
        
        $result = $this->sessionService->cleanupExpiredSessions();
        
        if ($result['success']) {
            $this->info('✅ Session cleanup completed successfully');
            $this->line('Expired orders: ' . $result['data']['expired_orders']);
            $this->line('Freed tables: ' . implode(', ', $result['data']['freed_tables']));
        } else {
            $this->error('❌ Session cleanup failed: ' . $result['message']);
        }
    }
}
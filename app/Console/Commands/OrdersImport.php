<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ImportOrderCsvChunkJob;

class OrdersImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from a CSV and queue them for processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('path');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);

        $chunkSize = 100;
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;

            if (count($rows) >= $chunkSize) {
                ImportOrderCsvChunkJob::dispatch($header, $rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            ImportOrderCsvChunkJob::dispatch($header, $rows);
        }

        fclose($handle);

        $this->info('Import queued successfully!');
    }
}

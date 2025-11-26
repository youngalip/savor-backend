<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * TableController
 * 
 * Handles table management (CRUD + QR generation)
 * Status is INFORMATIONAL only - does not block customer scans
 */
class TableController extends Controller
{
    private QRCodeService $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Get all tables with filters
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('tables')
                ->select('id', 'table_number', 'qr_code', 'status', 'qr_generated_at', 'created_at', 'updated_at');

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Search by table number
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('table_number', 'ILIKE', "%{$search}%");
            }

            // Pagination
            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $limit;

            // Get total count
            $total = $query->count();

            // Get paginated data
            $tables = $query->orderBy('table_number', 'ASC')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Add full QR URL to each table
            $tables = $tables->map(function($table) {
                if ($table->qr_code) {
                    $table->qr_full_url = url($table->qr_code);
                }
                return $table;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tables' => $tables,
                    'pagination' => [
                        'total' => $total,
                        'page' => (int) $page,
                        'limit' => (int) $limit,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tables',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single table by ID
     */
    public function show($id)
    {
        try {
            $table = DB::table('tables')->where('id', $id)->first();

            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table not found'
                ], 404);
            }

            // Add full QR URL
            if ($table->qr_code) {
                $table->qr_full_url = url($table->qr_code);
            }

            return response()->json([
                'success' => true,
                'data' => $table
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Get table QR code with full URL and scan URL
     */
    public function getTableQR($id)
    {
        try {
            $table = DB::table('tables')->where('id', $id)->first();

            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table not found'
                ], 404);
            }

            // Generate full URL for QR code image
            $qrImageUrl = null;
            if ($table->qr_code) {
                $qrImageUrl = url($table->qr_code);
            }

            // Generate scan URL (what's encoded in the QR)
            $scanUrl = null;
            if ($table->qr_code) {
                // Extract QR code from path
                $qrCodeValue = basename($table->qr_code, '.svg');
                $qrCodeValue = basename($qrCodeValue, '.png');
                $scanUrl = config('app.frontend_url', url('/')) . '?qr=' . $qrCodeValue;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'table' => $table,
                    'qr_image_url' => $qrImageUrl,  // URL to download/view the QR image
                    'scan_url' => $scanUrl,          // URL that QR code links to
                    'qr_exists' => $this->qrCodeService->qrExists($table->qr_code ?? '')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch table QR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Get QR code as base64 (useful for direct display)
     */
    public function getQRBase64($id)
    {
        try {
            $table = DB::table('tables')->where('id', $id)->first();

            if (!$table || !$table->qr_code) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code not found'
                ], 404);
            }

            $base64 = $this->qrCodeService->getQRAsBase64($table->qr_code);

            if (!$base64) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to read QR code file'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'table_id' => $table->id,
                    'table_number' => $table->table_number,
                    'qr_base64' => $base64
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get QR as base64',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new table
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string|max:20|unique:tables,table_number'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tableId = DB::table('tables')->insertGetId([
                'table_number' => $request->table_number,
                'qr_code' => '',
                'status' => 'Free',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Generate QR code
            $qrData = $this->qrCodeService->generateTableQR($tableId, null, 'svg');

            // Update table with QR code path
            DB::table('tables')->where('id', $tableId)->update([
                'qr_code' => $qrData['qr_path'],
                'qr_generated_at' => now()
            ]);

            DB::commit();

            $table = DB::table('tables')->where('id', $tableId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Table created successfully',
                'data' => [
                    'table' => $table,
                    'qr_url' => $qrData['qr_url'],
                    'qr_svg' => $qrData['qr_svg'] ?? null,
                    'qr_image_url' => url($qrData['qr_path'])
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing table
     */
    public function update(Request $request, $id)
    {
        if (!DB::table('tables')->where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'table_number' => 'string|max:20|unique:tables,table_number,' . $id,
            'status' => 'in:Free,Occupied'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = ['updated_at' => now()];

            if ($request->filled('table_number')) $updateData['table_number'] = $request->table_number;
            if ($request->filled('status')) $updateData['status'] = $request->status;

            DB::table('tables')->where('id', $id)->update($updateData);

            DB::commit();

            $table = DB::table('tables')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Table updated successfully',
                'data' => $table
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete table
     */
    public function destroy($id)
    {
        try {
            $table = DB::table('tables')->where('id', $id)->first();

            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table not found'
                ], 404);
            }

            // Delete QR code image if exists
            if ($table->qr_code) {
                $this->qrCodeService->deleteQRCode($table->qr_code);
            }

            DB::table('tables')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Table deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete table',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate QR code for specific table
     */
    public function generateQRCode($id)
    {
        try {
            $table = DB::table('tables')->where('id', $id)->first();

            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table not found'
                ], 404);
            }

            $qrData = $this->qrCodeService->generateTableQR($id, $table->qr_code, 'svg');

            DB::table('tables')->where('id', $id)->update([
                'qr_code' => $qrData['qr_path'],
                'qr_generated_at' => now(),
                'updated_at' => now()
            ]);

            $table = DB::table('tables')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'QR code generated successfully',
                'data' => [
                    'table' => $table,
                    'qr_url' => $qrData['qr_url'],
                    'qr_svg' => $qrData['qr_svg'] ?? null,
                    'qr_image_url' => url($qrData['qr_path'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk generate QR codes
     */
    public function bulkGenerateQR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_ids' => 'required|array',
            'table_ids.*' => 'integer|exists:tables,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $results = [];
            $errors = [];

            foreach ($request->table_ids as $tableId) {
                try {
                    $table = DB::table('tables')->where('id', $tableId)->first();

                    if (!$table) {
                        $errors[] = "Table ID {$tableId} not found";
                        continue;
                    }

                    $qrData = $this->qrCodeService->generateTableQR($tableId, $table->qr_code, 'svg');

                    DB::table('tables')->where('id', $tableId)->update([
                        'qr_code' => $qrData['qr_path'],
                        'qr_generated_at' => now(),
                        'updated_at' => now()
                    ]);

                    $results[] = [
                        'table_id' => $tableId,
                        'table_number' => $table->table_number,
                        'qr_code' => $qrData['qr_path'],
                        'qr_url' => $qrData['qr_url'],
                        'qr_svg' => $qrData['qr_svg'] ?? null,
                        'qr_image_url' => url($qrData['qr_path'])
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Failed to generate QR for table ID {$tableId}: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => count($errors) === 0,
                'message' => count($errors) === 0 
                    ? 'QR codes generated successfully' 
                    : 'Some QR codes failed to generate',
                'data' => [
                    'generated' => $results,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
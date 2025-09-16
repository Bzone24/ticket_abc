<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CrossAbDataTable;
use App\DataTables\CrossAcDataTable;
use App\DataTables\CrossBcDataTable;
use App\DataTables\DrawProfilLossDataTable;
use App\Http\Controllers\Controller;
use App\Models\DrawDetail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    // public function index(DrawProfilLossDataTable $dataTable, Request $request)
    // {
    //     $today = Carbon::today('UTC');
    //     $today_1 = Carbon::today('Asia/Kolkata');

    //     $data = [
    //         'total_shopkeepers' => User::count(), // This is none filtered data for range filter
    //         'total_tickets' => Ticket::whereDate('created_at', $today)->count(),
    //         'total_claims' => DrawDetail::where('date', $today_1)
    //             ->sum('claim'),
    //         'total_cross_amt' => DrawDetail::where('date', $today_1)->sum('total_cross_amt'),
    //         'total_cross_claim' => DrawDetail::where('date', $today_1)->sum('claim_ab')
    //         + DrawDetail::where('date', $today_1)->sum('claim_ac')
    //        + DrawDetail::where('date', $today_1)->sum('claim_bc'),

    //         'claimed' => DrawDetail::where('date', $today_1)
    //             ->where('claim', '!=', 0)
    //             ->count(),

    //     ];

    //     return $dataTable->render('admin.dashboard.index', compact('data'));
    // }


    public function index(DrawProfilLossDataTable $dataTable, Request $request)
    {
        $today = Carbon::today('UTC');
        $today_1 = Carbon::today('Asia/Kolkata');

        $gameId = $request->input('game_id'); // 🔹 Get selected game
        $games = \App\Models\Game::all();

        $ticketQuery = Ticket::whereDate('created_at', $today);
        $drawDetailQuery = DrawDetail::where('date', $today_1);

        if ($gameId) {
            $ticketQuery->where('game_id', $gameId);
            $drawDetailQuery->where('game_id', $gameId);
        }

        $data = [
            'total_shopkeepers' => User::count(),
            'total_tickets' => $ticketQuery->count(),
            'total_claims' => (clone $drawDetailQuery)->sum('claim'),
            'total_cross_amt' => (clone $drawDetailQuery)->sum('total_cross_amt'),
            'total_cross_claim' => (clone $drawDetailQuery)->sum('claim_ab')
                + (clone $drawDetailQuery)->sum('claim_ac')
                + (clone $drawDetailQuery)->sum('claim_bc'),
            'claimed' => (clone $drawDetailQuery)->where('claim', '!=', 0)->count(),
        ];

        return $dataTable->render('admin.dashboard.index', compact('data', 'games', 'gameId'));
    }


    public function crossAbc(CrossAbDataTable $dataTable, CrossAcDataTable $crossAcDataTable, CrossBcDataTable $crossBcDataTable, Request $request)
    {
        $drawDetail = DrawDetail::findOrFail($request->get('draw_detail_id'));

        return $dataTable->render('admin.dashboard.cross-abc-details', [
            'drawDetail' => $drawDetail,
            'crossAcDataTable' => $crossAcDataTable->html(),
            'crossBcDataTable' => $crossBcDataTable->html(),
        ]);
    }

    public function getCrossAcList(CrossAcDataTable $crossAcDataTable, CrossBcDataTable $crossBcDataTable)
    {

        return $crossAcDataTable->render('admin.dashboard.cross-abc-details', compact('crossBcDataTable'));
    }

    public function getCrossBcList(CrossAcDataTable $crossAcDataTable, CrossBcDataTable $crossBcDataTable)
    {
        return $crossBcDataTable->render('admin.dashboard.cross-abc-details', compact('crossAcDataTable'));
    }

    public function totalQtyDetailList(DrawDetail $drawDetail)
    {
        return view('admin.dashboard.total-qty-details-table', compact('drawDetail'));
    }
}

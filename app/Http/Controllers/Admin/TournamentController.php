<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameCategory;
use App\Models\GameTournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stevebauman\Purify\Facades\Purify;
use Illuminate\Support\Facades\Http;
use App\Models\ContentOdd;


class TournamentController extends Controller
{
    public function listTournament()
    {
        $data['tournaments'] = GameTournament::with('gameCategory')->orderBy('id','desc')->get();
        $data['categories'] = GameCategory::whereStatus(1)->orderBy('name','asc')->get();
        $data['tournments_from_odds'] = json_decode($this::tournamentFromOdds());
        return view('admin.tournament.list', $data);
    }

    public function tournamentFromOdds()
    {
        $contentCtrl = new ContentController();
        $content = $contentCtrl->fetchFromOdds('/sports', '');
        if ($content == null)
        {
            return "[]";
        }

        $sports = json_decode($content);
        $added_tournaments = GameTournament::orderBy('id', 'desc')->get()->pluck('name')->toArray();
        $categories = GameCategory::whereStatus(1)->orderBy('name','asc')->get()->pluck('name')->toArray();

        $tournaments = [];
        foreach ($sports as &$sport) {
            $exist = array_search($sport->title, $added_tournaments);
            if ($exist !== false) continue;

            $category = array_search($sport->group, $categories);
            if ($category === false) continue;

            $tournaments[$sport->title] = [
                'key' => $sport->title,
                'title' => $sport->title,
                'group' => $sport->group,
                'active' => $sport->active,
                'has_outrights' => $sport->has_outrights,
            ];
        }

        return json_encode(array_values($tournaments));
    }

    public function storeTournament(Request $request)
    {

        $purifiedData = Purify::clean($request->except('image', '_token', '_method'));
        $rules = [
            'name' => 'required|max:40',
            'category' => 'required',
        ];
        $message = [
            'name.required' => 'Name field is required',
            'category.required' => 'Category field is required',
        ];

        $validate = Validator::make($purifiedData, $rules, $message);

        if ($validate->fails()) {
            return back()->withInput()->withErrors($validate);
        }

        try{

            $gameTournament = new GameTournament();

            if ($request->has('name')) {
                $gameTournament->name = @$purifiedData['name'];
            }
            if ($request->has('category')) {
                $gameTournament->category_id = $request->category;
            }

            $gameTournament->status = isset($purifiedData['status']) == 'true' ? 1 : 0;

            $gameTournament->save();
            return back()->with('success', 'Successfully Saved');

        }catch (\Exception $e){
            return back();
        }
    }

    public function storeTournamentsFromOdd(Request $request)
    {

        $names = $request->get('checks_add');
        $added_tournaments = GameTournament::orderBy('id', 'desc')->get()->pluck('name')->toArray();
        $categories = GameCategory::whereStatus(1)->orderBy('name','asc')->get()->toArray();

        $tournaments = [];
        for($i = 0; $i < count($names); $i++)
        {
            list($name, $group, $status) = explode(":", $names[$i]);
            $exist = array_search($name, $added_tournaments);
            $id = array_search($group, array_column($categories, 'name'));

            $tournament = [
                'name' => $name,
                'category_id' => $categories[$id]['id'],
                'status' => $status,
            ];
            array_push($tournaments, $tournament);
        }

        try {

            foreach($tournaments as $tournament)
            {
                $gameTournament = new GameTournament();
                $gameTournament->name = $tournament['name'];
                $gameTournament->category_id = $tournament['category_id'];
                $gameTournament->status = $tournament['status'];

                $gameTournament->save();
            }

            return back()->with('success', 'Successfully Saved');

        } catch (\Exception $e) {
            return back();
        }
    }

    public function updateTournament(Request $request,$id)
    {
        $purifiedData = Purify::clean($request->except('image', '_token', '_method'));
        $rules = [
            'name' => 'required|max:40',
            'category' => 'required',
        ];
        $message = [
            'name.required' => 'Name field is required',
            'category.required' => 'Category field is required',
        ];

        $validate = Validator::make($purifiedData, $rules, $message);

        if ($validate->fails()) {
            return back()->withInput()->withErrors($validate);
        }

        try{
            $gameTournament = GameTournament::findOrFail($id);

            if ($request->has('name')) {
                $gameTournament->name = @$purifiedData['name'];
            }

            if ($request->has('category')) {
                $gameTournament->category_id = $request->category;
            }

            $gameTournament->status = isset($purifiedData['status']) == 'true' ? 1 : 0;

            $gameTournament->save();
            return back()->with('success', 'Successfully Updated');

        }catch (\Exception $e){
            return back();
        }
    }

    public function deleteTournament($id)
    {
        $gameTournament = GameTournament::with('gameMatch')->findOrFail($id);

        if (0 < count($gameTournament->gameMatch)) {
            session()->flash('warning', 'This tournament has a lot of match');
            return back();
        }

        $gameTournament->delete();
        return back()->with('success', 'Successfully deleted');
    }

    public function activeMultiple(Request $request)
    {
        if ($request->strIds == null) {
            session()->flash('error', 'You do not select ID.');
            return response()->json(['error' => 1]);
        } else {
            GameTournament::whereIn('id', $request->strIds)->update([
                'status' => 1,
            ]);
            session()->flash('success', 'Tournament Has Been Active');
            return response()->json(['success' => 1]);
        }

    }

    public function deActiveMultiple(Request $request)
    {
        if ($request->strIds == null) {
            session()->flash('error', 'You do not select ID.');
            return response()->json(['error' => 1]);
        } else {
            GameTournament::whereIn('id', $request->strIds)->update([
                'status' => 0,
            ]);
            session()->flash('success', 'Tournament Has Been Deactive');
            return response()->json(['success' => 1]);
        }
    }
}

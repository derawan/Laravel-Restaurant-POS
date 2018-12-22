<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Http\Resources\User as UserResource;
use Illuminate\Support\Facades\DB;

use App\User;
use Hash;

class UserControllerAPI extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('page')) {
            return UserResource::collection(User::paginate(50));
        } else {
            return UserResource::collection(User::all());
        }
    }

    public function show($id)
    {
        return new UserResource(User::find($id));
    }

    public function store(Request $request)
    {
        $request->validate([
                'name' => 'required|min:3|regex:/^[A-Za-záàâãéèêíóôõúçÁÀÂÃÉÈÍÓÔÕÚÇ ]+$/',
                'email' => 'required|email|unique:users,email',
                'age' => 'integer|between:18,75',
                'password' => 'min:3'
            ]);
        $user = new User();
        $user->fill($request->all());
        $user->password = Hash::make($user->password);
        $user->save();
        return response()->json(new UserResource($user), 201);
    }

    public function blockUnblock(Request $request, $id)
    {
        if($request->operation == 1) {
            Table::where('id', $id)->update(array('blocked' => 1));
        } else if($request->operation == 0) {
            Table::where('id', $id)->update(array('blocked' => 0));
        }
    }

    public function update(Request $request, $id)
    {
        $request->merge(array_map('trim', $request->all()));

        $data = $request->validate([
            'name' => 'required|string|min:3|regex:/^[A-Za-záàâãéèêíóôõúçÁÀÂÃÉÈÍÓÔÕÚÇ ]+$/',
            'username' => 'required|string|min:2|regex:/^[A-Za-záàâãéèêíóôõúçÁÀÂÃÉÈÍÓÔÕÚÇ0-9_-]+$/|unique:users',
        ]);

        $user = User::findOrFail($id);
        $user->update($data);
        return new UserResource($user);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(null, 204);
    }
    public function emailAvailable(Request $request)
    {
        $totalEmail = 1;
        if ($request->has('email') && $request->has('id')) {
            $totalEmail = DB::table('users')->where('email', '=', $request->email)->where('id', '<>', $request->id)->count();
        } else if ($request->has('email')) {
            $totalEmail = DB::table('users')->where('email', '=', $request->email)->count();
        }
        return response()->json($totalEmail == 0);
    }

    public function myProfile(Request $request)
    {
        return new UserResource($request->user());
    }

    public function uploadPhoto(Request $request, $id) {
        $data = $request->validate([
            'photo' => 'required|image',
        ]);

        $user = User::findOrFail($id);
        $file = $data['photo'];

        if (!is_null($user->photo_url)) {
            Storage::disk('public')->delete('profiles/'.$user->photo_url);
        }

        if (!Storage::disk('public')->exists('profiles/'.$file->hashname())) {
            $file->store('profiles', 'public');
        }

        $user->photo_url = $file->hashname();
        $user->save();
        return response()->json(["data" => $file->hashname()]);
    }

    public function toggleShift($id) {
        $user = User::findOrFail($id);
        if ($user->shift_active == 1) {
            $shift_active = 0;
            $user->last_shift_end = Carbon::now()->toDateTimeString();

        } else {
            $shift_active = 1;
            $user->last_shift_start = Carbon::now()->toDateTimeString();
        }

        $user->shift_active = $shift_active;
        $user->save();
        return response()->json(["data" => $user]);
    }
}

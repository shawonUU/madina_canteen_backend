<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use App\Http\Controllers\Controller;

class PermissionController extends Controller
{

    // Get All Permissions
    public function index()
    {
        return response()->json(
            Permission::all()
        );
    }


    // Create Permission
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name'
        ]);


        $permission = Permission::create([
            'name' => $request->name
        ]);


        return response()->json([
            'message' => 'Permission created successfully',
            'data' => $permission
        ]);
    }


    // Update Permission
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $id
        ]);


        $permission = Permission::findOrFail($id);


        $permission->update([
            'name' => $request->name
        ]);


        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }



    // Delete Permission
    public function destroy($id)
    {
        Permission::findOrFail($id)->delete();


        return response()->json([
            'message' => 'Permission deleted'
        ]);
    }

}
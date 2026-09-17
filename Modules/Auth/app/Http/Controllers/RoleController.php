<?php
namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    // Role List
    public function index()
    {
        return response()->json(
            Role::with('permissions')->get()
        );
    }



    // Create Role + Permission Assign
    public function store(Request $request)
    {

        $request->validate([
            'name'=>'required|unique:roles,name',
            'permissions'=>'array'
        ]);


        $role = Role::create([
            'name'=>$request->name,
            'guard_name' => 'sanctum',
        ]);


        if($request->permissions)
        {
            $role->syncPermissions(
                $request->permissions
            );
        }


        return response()->json([
            'message'=>'Role created successfully',
            'data'=>$role->load('permissions')
        ]);

    }




    // Update Role + Permission Update
    public function update(Request $request,$id)
    {

        $request->validate([
            'name'=>'required|unique:roles,name,'.$id,
            'permissions'=>'array'
        ]);


        $role = Role::findOrFail($id);


        $role->update([
            'name'=>$request->name,
            'guard_name' => 'sanctum',
        ]);



        if($request->permissions)
        {
            $role->syncPermissions(
                $request->permissions
            );
        }


        return response()->json([
            'message'=>'Role updated successfully',
            'data'=>$role->load('permissions')
        ]);

    }



    public function destroy($id)
    {
        Role::findOrFail($id)->delete();

        return response()->json([
            'message'=>'Role deleted'
        ]);
    }
}
<?
namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserPermissionController extends Controller
{
   


    // Assign Direct Permission To User

    public function assignPermission(Request $request)
    {

        $request->validate([
            'user_id'=>'required',
            'permissions'=>'required|array'
        ]);
        
        $user = User::findOrFail(
            $request->user_id
        );

        $user->syncPermissions(
            $request->permissions
        );

        return response()->json([
            'message'=>'Permission assigned successfully',
            'user'=>$user->load('permissions')
        ]);

    }


    public function removePermission(Request $request)
    {

        $user = User::findOrFail(
            $request->user_id
        );


        $user->revokePermissionTo(
            $request->permission
        );


        return response()->json([
            'message'=>'Permission removed'
        ]);

    }
}
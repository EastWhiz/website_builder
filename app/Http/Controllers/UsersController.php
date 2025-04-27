<?php

namespace App\Http\Controllers;

use App\Models\EditedTemplate;
use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $templates = User::with('editedTemplates')->when($request->get('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', '%' . $request->q . '%');
            });
        })->when($request->get('sort'), function ($q) use ($request) {
            $q->orderBy(...explode(' ', $request->get('sort')));
        })->cursorPaginate($request->page_count);
        return sendResponse(true, 'Users retrieved successfully!', $templates);
    }

    public function userThemesList(Request $request, $id)
    {
        $templates = EditedTemplate::where('user_id', $id)->with(['template', 'user'])->when($request->get('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->orWhere('name', 'LIKE', '%' . $request->q . '%');
            });
        })->when($request->get('sort'), function ($q) use ($request) {
            $q->orderBy(...explode(' ', $request->get('sort')));
        })->cursorPaginate($request->page_count);
        return sendResponse(true, 'User themes retrieved successfully!', $templates);
    }
}

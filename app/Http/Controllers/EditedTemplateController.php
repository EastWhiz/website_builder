<?php

namespace App\Http\Controllers;

use App\Models\EditedTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EditedTemplateController extends Controller
{
    public function saveTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'main_html' => 'required',
        ], []);

        if ($validator->fails())
            return simpleValidate($validator);

        if ($request->edit_id != false) {
            $editedTemplate = EditedTemplate::find($request->edit_id);
        } else {
            $editedTemplate = new EditedTemplate;
        }

        $editedTemplate->name = $request->name;
        $editedTemplate->main_html = $request->main_html;
        $editedTemplate->template_id = $request->template_id;
        $editedTemplate->user_id = Auth::user()->id;
        $editedTemplate->save();

        return sendResponse(true, "Edited Template Saved Successfully!");
    }
}

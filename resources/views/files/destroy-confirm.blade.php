@if(!$file->isDir())
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h4 class="modal-title" id="modal-title">@lang('file-db::file-db.delete-file')</h4>
</div>
<div class="modal-body" id="modal-body">
<h3>@lang('file-db::file-db.messages.really-delete')</h3>
@if($resources)
<span class="label label-danger">@lang('file-db::file-db.caution')</span> <span class="text-danger">@lang('file-db::file-db.messages.file-has-following-dependencies')</span>
@include($dependencies_template)
@endif
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('file-db::file-db.cancel')</button>
    <button type="button" class="btn btn-danger" onclick="deleteFile('{{ URL::route('files.destroy', [$file->getId()]) }}');">@lang('file-db::file-db.delete')</button>
</div>
@elseif($file->isEmpty())
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h4 class="modal-title" id="modal-title">@lang('file-db::file-db.delete-dir')</h4>
</div>
<div class="modal-body" id="modal-body">
<h3>@lang('file-db::file-db.messages.really-delete-dir')</h3>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('file-db::file-db.cancel')</button>
    <button type="button" class="btn btn-danger" onclick="deleteFile('{{ URL::route('files.destroy', [$file->getId()]) }}');">@lang('file-db::file-db.delete')</button>
</div>
@else
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h4 class="modal-title" id="modal-title">@lang('file-db::file-db.delete-dir')</h4>
</div>
<div class="modal-body" id="modal-body">
<h4>@lang('file-db::file-db.messages.cannot-delete-nonempty-dir')</h4>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('file-db::file-db.cancel')</button>
</div>
@endif

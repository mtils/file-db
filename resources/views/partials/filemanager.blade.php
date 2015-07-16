    <div class="filemanager">
        <div class="buttons">
            <button type="button" class="btn btn-default"  onclick="$('.filemanager .upload-file').show(); $('.filemanager .buttons').hide();"><i class="fa fa-plus-circle"></i> @lang('file-db::file-db.upload')</button>
            <button type="button" class="btn btn-default" onclick="$('.filemanager .new-dir').show(); $('.filemanager .buttons').hide();"><i class="fa fa-file-o"></i> @lang('file-db::file-db.new-folder')</button>
            <button type="button" class="btn btn-success pull-right" onclick="window.location.href='{{ $toRoute('sync', $dir->id) }}';"><i class="fa fa-repeat"></i> @lang('file-db::file-db.sync')</button>
        </div>
        <form action="{{ $toRoute('upload', $dir->id) }}" method="post" enctype="multipart/form-data">
            <div class="upload-file input-group" style="display: none;">
                <label for="filemanagerUpload"></label>
                <input type="file" name="uploadedFile" id="filemanagerUpload"/>
                @foreach($params as $key=>$value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}"/>
                @endforeach
                <span class="input-group-btn">
                    <button class="btn btn-primary" type="button" onclick="this.form.submit();">@lang('file-db::file-db.upload')</button>
                </span>
                <span class="input-group-btn">
                    <button class="btn btn-default" type="button" onclick="$('.filemanager .upload-file').hide(); $('.filemanager .buttons').show();">@lang('file-db::file-db.cancel')</button>
                </span>
            </div>
        </form>
        <form action="{{ $toRoute('store', $dir->id) }}" method="post">
            <div class="input-group new-dir" style="display: none;">
                <input type="text" class="form-control" name="folderName" placeholder="@lang('file-db::file-db.file-properties.dir-name')">
                @foreach($params as $key=>$value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}"/>
                @endforeach
                <span class="input-group-btn">
                    <button class="btn btn-primary" type="button" onclick="this.form.submit();">@lang('file-db::file-db.new-folder-inline')</button>
                </span>
                <span class="input-group-btn">
                    <button class="btn btn-default" type="button" onclick="$('.filemanager .new-dir').hide(); $('.filemanager .buttons').show();">@lang('file-db::file-db.cancel')</button>
                </span>
            </div>
        </form>
        <ol class="breadcrumb">
            @foreach($parents as $parent)
            <li><a href="{{ $toRoute('index', $parent->id) }}">{{ $parent->getTitle() }}</a></li>
            @endforeach
            <li class="active">{{ $dir->getTitle() }}</li>
        </ol>
        @if(Session::has('file-db-message'))
        <? list($message, $state) = Session::get('file-db-message') ?>
        <div class="alert alert-{{ $state }}  alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <strong>{{ $message }}</strong>
        </div>
        @endif
        @if(!count($dir->children()))
            <div class="jumbotron" style="padding-left: 10px;"><h2>@lang('file-db::file-db.messages.folder-empty')</h2></div>
        @else
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <th>@lang('file-db::file-db.file-properties.name')</th>
                        <th>@lang('file-db::file-db.file-properties.updated_at')</th>
                        <th>@lang('file-db::file-db.actions-title')</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($dir->children() as $file)
                    @if($file->isDir())
                        <? $attributes = new FormObject\Attributes([
                                'href'=> $toRoute('index', $file->id)
                           ]);
                        ?>
                        <?php $icon = 'fa-folder-o' ?>
                    @else
                        <? $attributes = new FormObject\Attributes(
                                $attributeSetter($file)
                            ); ?>
                        <?php $url = $file->url ?>
                        <?php $icon = 'fa-file-o' ?>
                        <?php $class = '' ?>
                    @endif
                    <? $attributes['data-id'] = $file->id ?>
                    <tr>
                        <td class="thumb">
                            <a {!! $attributes !!}>
                            @if(starts_with($file->getMimeType(),'image/'))
                                <div class="crop" style="background-image: url('{{ $file->url }}');" data-id="{{ $file->id }}"></div>
                            @else
                                <i class="fa {{ $icon }}"></i>
                            @endif
                            </a>
                        </td>
                        <td><a {!! $attributes !!}>{{ $file->getTitle() }}</a></td>
                        <td>{{ $file->updated_at }}</td>
                        <td>
                            <!-- Form::open(array('route' => '', 'method' => 'delete')) -->
                                <button type="submit" data-delete-confirm="{{ URL::route('files.destroy-confirm', [$file->getId()]) }}" class="btn btn-link btn-mini delete"><i class="glyphicon glyphicon-trash"></i></button>
                            <!-- Form::close() -->
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="modal fade" id="modal-container" tabindex="-1" role="dialog" aria-labelledby="uploadtitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" id="modal-content">
            </div>
        </div>
    </div> 
            <table class="table table-hover small condensed">
                <thead>
                    <tr>
                        <th>@lang('file-db::file-db.dependency-properties.category')</th>
                        <th>@lang('file-db::file-db.dependency-properties.title')</th>
                        <th>@lang('file-db::file-db.dependency-properties.id')</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($resources as $resource)
                    <tr>
                        <td>{{ $resource->category() }}</td>
                        <td>{{ $resource->title() }}</td>
                        <td>{{ $resource->id() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
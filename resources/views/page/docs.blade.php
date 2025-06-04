@extends('layouts.app')

@section('content')
    <h2 class="text-xl font-bold mb-4">相關文件</h2>

        <ul class="list-disc ml-6 space-y-2 mb-6">
            <li>
                <a href="{{ route('file.view', ['path' => 'invasi_files/files/survey-guide.pdf']) }}" target="_blank">
                    調查手冊
                </a>
            </li>
        </ul>
        <hr class="hr-mist">
        <ul class="list-disc ml-6 space-y-2">
            <li>
                    <a href="{{ route('file.view', ['path' => 'invasi_files/files/生育地類型紀錄表.pdf']) }}" target="_blank">
                        生育地類型紀錄表
                    </a>
                </li>
            <li>
                <a href="{{ route('file.view', ['path' => 'invasi_files/files/小樣方調查紀錄表.pdf']) }}" target="_blank">
                    小樣方調查紀錄表
                </a>
            </li>
            <li>
                <a href="{{ route('file.view', ['path' => 'invasi_files/files/植物調查空白紀錄表.pdf']) }}" target="_blank">
                    植物調查空白紀錄表
                </a>
            </li>
        </ul>


    

@endsection

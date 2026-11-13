{{-- Voyar facts on the staff service page. --}}
<div class="card" style="margin-top:16px">
    <div class="card-header"><strong>Voyar</strong></div>
    <div class="card-body">
        <table class="table table-sm" style="margin:0">
            @foreach(['voyar_id' => 'Voyar ID', 'voyar_kind' => 'Tür', 'voyar_billing' => 'Faturalandırma', 'voyar_status' => 'Voyar durumu', 'voyar_ip' => 'IP', 'voyar_os' => 'İşletim sistemi', 'voyar_location' => 'Lokasyon', 'voyar_expires_at' => 'Voyar bitiş', 'voyar_app' => 'Seçilen uygulama', 'voyar_app_task' => 'Uygulama görevi', 'ftp_host' => 'FTP sunucusu'] as $key => $label)
                @if(!empty($data[$key]))
                <tr><th style="width:40%">{{ $label }}</th><td><code>{{ $data[$key] }}</code></td></tr>
                @endif
            @endforeach
        </table>
        @if(empty($data['voyar_id']))<p class="text-muted" style="margin:8px 0 0">Bu hizmet henüz Voyar'da oluşturulmadı.</p>@endif
    </div>
</div>

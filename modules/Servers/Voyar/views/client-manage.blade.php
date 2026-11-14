{{--
    The server's management tabs (VoyarManage): each tab loads its list from
    voyar.client.section and acts through voyar.client.act.
--}}
@php
    $tabs = ['backups' => 'ri-archive-line', 'firewall' => 'ri-shield-line', 'graphs' => 'ri-line-chart-line', 'iso' => 'ri-disc-line',
             'rdns' => 'ri-global-line', 'access' => 'ri-key-line', 'waf' => 'ri-spam-2-line', 'tasks' => 'ri-list-check'];
    $t = __('voyar::voyar.manage');
@endphp
<style>
    .vm-tabs{display:flex;flex-wrap:wrap;gap:2px;border-bottom:1px solid var(--border);margin:20px 0 0}
    .vm-tabs button{display:inline-flex;align-items:center;gap:6px;padding:10px 12px;margin-bottom:-1px;border:0;border-bottom:2px solid transparent;background:none;font:inherit;font-size:13.5px;font-weight:600;color:var(--muted);cursor:pointer}
    .vm-tabs button.on{color:var(--primary);border-bottom-color:var(--primary)}
    .vm-pane{background:var(--card);border:1px solid var(--border);border-top:0;border-radius:0 0 8px 8px;padding:18px 20px;min-height:120px}
    .vm-table{width:100%;border-collapse:collapse;font-size:13.5px}
    .vm-table th{text-align:left;font-size:12px;color:var(--muted);font-weight:600;padding:8px 10px;border-bottom:1px solid var(--border)}
    .vm-table td{padding:10px;border-bottom:1px solid var(--border);vertical-align:middle}
    .vm-row{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-top:14px}
    .vm-row label{display:grid;gap:4px;font-size:12px;color:var(--muted);font-weight:600}
    .vm-row input,.vm-row select{padding:7px 10px;border:1px solid var(--border);border-radius:6px;font:inherit;font-size:13px;background:var(--card);color:var(--text)}
    .vm-msg{padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:13px}
    .vm-msg.ok{background:#e7f6ef;color:#12805c}.vm-msg.bad{background:#fdecec;color:#c4323a}
    .vm-note{color:var(--muted);font-size:13px;margin:0 0 12px}
    .vm-chart{width:100%;height:180px;border:1px solid var(--border);border-radius:6px;background:var(--bg)}
</style>

<div x-data="voyarManage(@js(route('voyar.client.section', [$service, '__S__'])), @js(route('voyar.client.act', [$service, '__A__'])), @js(csrf_token()), @js($t))" x-init="open('backups')">
    <div class="vm-tabs">
        @foreach($tabs as $key => $icon)
        <button type="button" :class="{ on: tab === '{{ $key }}' }" @click="open('{{ $key }}')"><i class="{{ $icon }}"></i> {{ $t['tabs'][$key] }}</button>
        @endforeach
    </div>
    <div class="vm-pane">
        <template x-if="msg"><div class="vm-msg" :class="msgOk ? 'ok' : 'bad'" x-text="msg"></div></template>
        <template x-if="loading"><p class="vm-note">{{ $t['loading'] }}</p></template>

        {{-- Backups --}}
        <div x-show="tab === 'backups' && !loading">
            <p class="vm-note">{{ $t['backups_text'] }}</p>
            <button class="pn-btn pn-btn-sm pn-btn-primary btn btn-primary btn-sm" @click="act('backup.create', {}, @js($t['backup_create_confirm']))"><i class="ri-add-line"></i> {{ $t['backup_create'] }}</button>
            <table class="vm-table" style="margin-top:12px" x-show="items.length">
                <thead><tr><th>{{ $t['date'] }}</th><th>{{ $t['size'] }}</th><th></th></tr></thead>
                <tbody><template x-for="b in items" :key="b.volid"><tr>
                    <td x-text="date(b.ctime)"></td><td x-text="size(b.size)"></td>
                    <td style="text-align:right;white-space:nowrap">
                        <button class="btn btn-outline btn-xs" @click="act('backup.restore', { volid: b.volid }, @js($t['restore_confirm']))">{{ $t['restore'] }}</button>
                        <button class="btn btn-outline btn-xs" @click="act('backup.delete', { volid: b.volid }, @js($t['delete_confirm']))">{{ $t['delete'] }}</button>
                    </td>
                </tr></template></tbody>
            </table>
            <p class="vm-note" style="margin-top:12px" x-show="!items.length">{{ $t['empty'] }}</p>
        </div>

        {{-- Firewall --}}
        <div x-show="tab === 'firewall' && !loading">
            <p class="vm-note">{{ $t['firewall_text'] }}</p>
            <table class="vm-table" x-show="items.length">
                <thead><tr><th>#</th><th>{{ $t['direction'] }}</th><th>{{ $t['rule_action'] }}</th><th>{{ $t['proto'] }}</th><th>{{ $t['port'] }}</th><th>{{ $t['source'] }}</th><th>{{ $t['comment'] }}</th><th></th></tr></thead>
                <tbody><template x-for="r in items" :key="r.pos"><tr :style="r.enable ? '' : 'opacity:.5'">
                    <td x-text="r.pos"></td><td x-text="r.type"></td><td x-text="r.action"></td><td x-text="r.proto || '—'"></td>
                    <td x-text="r.dport || '—'"></td><td x-text="r.source || '—'"></td><td x-text="r.comment || ''"></td>
                    <td style="text-align:right;white-space:nowrap">
                        <template x-if="r.mutable">
                            <span>
                                <button class="btn btn-outline btn-xs" @click="act('firewall.toggle', { pos: r.pos })" x-text="r.enable ? @js($t['disable']) : @js($t['enable'])"></button>
                                <button class="btn btn-outline btn-xs" @click="act('firewall.delete', { pos: r.pos }, @js($t['delete_confirm']))">{{ $t['delete'] }}</button>
                            </span>
                        </template>
                    </td>
                </tr></template></tbody>
            </table>
            <form class="vm-row" @submit.prevent="act('firewall.create', Object.fromEntries(new FormData($event.target)))">
                <label>{{ $t['direction'] }}<select name="type"><option value="in">{{ $t['in'] }}</option><option value="out">{{ $t['out'] }}</option></select></label>
                <label>{{ $t['rule_action'] }}<select name="action"><option>ACCEPT</option><option>DROP</option><option>REJECT</option></select></label>
                <label>{{ $t['proto'] }}<select name="proto"><option value="tcp">TCP</option><option value="udp">UDP</option><option value="icmp">ICMP</option><option value="">{{ $t['any'] }}</option></select></label>
                <label>{{ $t['port'] }}<input name="dport" placeholder="443 / 8000:8100" size="10"></label>
                <label>{{ $t['source'] }}<input name="source" placeholder="203.0.113.0/24" size="16"></label>
                <label>{{ $t['comment'] }}<input name="comment" maxlength="60" size="14"></label>
                <button class="btn btn-primary btn-sm">{{ $t['add_rule'] }}</button>
            </form>
        </div>

        {{-- Graphs --}}
        <div x-show="tab === 'graphs' && !loading">
            <div style="display:flex;gap:6px;margin-bottom:10px">
                @foreach(\Modules\Servers\Voyar\VoyarManage::TIMEFRAMES as $tf)
                <button class="btn btn-xs" :class="frame === '{{ $tf }}' ? 'btn-primary' : 'btn-outline'" @click="frame = '{{ $tf }}'; open('graphs')">{{ $t['frames'][$tf] }}</button>
                @endforeach
            </div>
            <p class="vm-note"><span style="color:#5b4cf0">■</span> CPU % &nbsp; <span style="color:#12805c">■</span> {{ $t['memory'] }} %</p>
            <svg class="vm-chart" viewBox="0 0 600 180" preserveAspectRatio="none" x-html="chart()"></svg>
            <p class="vm-note" style="margin-top:8px" x-show="!points.length">{{ $t['empty'] }}</p>
        </div>

        {{-- ISO --}}
        <div x-show="tab === 'iso' && !loading">
            <p class="vm-note">{{ $t['iso_text'] }}</p>
            <button class="btn btn-outline btn-sm" @click="act('iso.unmount', {}, @js($t['unmount_confirm']))">{{ $t['unmount'] }}</button>
            <table class="vm-table" style="margin-top:12px" x-show="items.length">
                <thead><tr><th>ISO</th><th>{{ $t['size'] }}</th><th></th></tr></thead>
                <tbody><template x-for="i in items" :key="i.encryptedname"><tr>
                    <td x-text="i.volid"></td><td x-text="size(i.size)"></td>
                    <td style="text-align:right"><button class="btn btn-outline btn-xs" @click="act('iso.mount', { encryptedname: i.encryptedname }, @js($t['mount_confirm']))">{{ $t['mount'] }}</button></td>
                </tr></template></tbody>
            </table>
        </div>

        {{-- rDNS --}}
        <div x-show="tab === 'rdns' && !loading">
            <p class="vm-note">{{ $t['rdns_text'] }}</p>
            <template x-for="ip in items" :key="ip.ipaddress">
                <form class="vm-row" style="margin-top:8px" @submit.prevent="act('rdns.update', { ipaddress: ip.ipaddress, hostname: $event.target.hostname.value })">
                    <label>IP<input :value="ip.ipaddress" disabled size="16"></label>
                    <label>{{ $t['hostname'] }}<input name="hostname" :value="ip.rdns_hostname || ''" placeholder="mail.example.com" size="28"></label>
                    <button class="btn btn-primary btn-sm" :disabled="ip.cooldown_remaining_seconds > 0">{{ $t['save'] }}</button>
                    <small class="vm-note" style="margin:0" x-show="ip.queue_status" x-text="@js($t['queued']) + ' ' + (ip.queue_hostname || '')"></small>
                </form>
            </template>
        </div>

        {{-- Access: SSH / RDP --}}
        <div x-show="tab === 'access' && !loading">
            <template x-if="data.ssh">
                <div style="margin-bottom:16px">
                    <h4 style="font-size:14px;margin:0 0 6px">SSH</h4>
                    <p class="vm-note"><span x-text="data.ssh.active ? @js($t['on']) : @js($t['off'])"></span> · {{ $t['port'] }} <b x-text="data.ssh.port"></b>
                        <template x-if="data.sshpw"><span> · {{ $t['password_login'] }}: <b x-text="data.sshpw.enabled ? @js($t['on']) : @js($t['off'])"></b></span></template></p>
                    <div class="vm-row" style="margin-top:0">
                        <button class="btn btn-outline btn-sm" @click="act(data.ssh.active ? 'ssh.disable' : 'ssh.enable', {}, data.ssh.active ? @js($t['ssh_off_confirm']) : null)" x-text="data.ssh.active ? @js($t['ssh_disable']) : @js($t['ssh_enable'])"></button>
                        <template x-if="data.sshpw"><button class="btn btn-outline btn-sm" @click="act(data.sshpw.enabled ? 'sshpw.disable' : 'sshpw.enable', {}, data.sshpw.enabled ? @js($t['sshpw_off_confirm']) : null)" x-text="data.sshpw.enabled ? @js($t['sshpw_disable']) : @js($t['sshpw_enable'])"></button></template>
                        <form class="vm-row" style="margin:0" @submit.prevent="act('ssh.port', { port: $event.target.port.value }, @js($t['port_confirm']))"><label>{{ $t['new_port'] }}<input name="port" type="number" min="1" max="65535" size="6"></label><button class="btn btn-primary btn-sm">{{ $t['save'] }}</button></form>
                    </div>
                </div>
            </template>
            <template x-if="data.rdp">
                <div>
                    <h4 style="font-size:14px;margin:0 0 6px">{{ $t['rdp'] }}</h4>
                    <p class="vm-note"><span x-text="data.rdp.active ? @js($t['on']) : @js($t['off'])"></span> · {{ $t['port'] }} <b x-text="data.rdp.port"></b></p>
                    <div class="vm-row" style="margin-top:0">
                        <button class="btn btn-outline btn-sm" @click="act(data.rdp.active ? 'rdp.disable' : 'rdp.enable')" x-text="data.rdp.active ? @js($t['rdp_disable']) : @js($t['rdp_enable'])"></button>
                        <form class="vm-row" style="margin:0" @submit.prevent="act('rdp.port', { port: $event.target.port.value }, @js($t['port_confirm']))"><label>{{ $t['new_port'] }}<input name="port" type="number" min="1" max="65535" size="6"></label><button class="btn btn-primary btn-sm">{{ $t['save'] }}</button></form>
                    </div>
                </div>
            </template>
            <p class="vm-note" x-show="!data.ssh && !data.rdp">{{ $t['access_none'] }}</p>
        </div>

        {{-- WAF --}}
        <div x-show="tab === 'waf' && !loading">
            <p class="vm-note">{{ $t['waf_text'] }}</p>
            <table class="vm-table" x-show="items.length">
                <thead><tr><th>{{ $t['ruleset'] }}</th><th>IP</th><th></th></tr></thead>
                <tbody><template x-for="row in wafRows()" :key="row.key"><tr>
                    <td><b x-text="row.name"></b><br><small class="text-muted" x-text="row.description"></small></td>
                    <td x-text="row.ip"></td>
                    <td style="text-align:right"><button class="btn btn-xs" :class="row.enabled ? 'btn-primary' : 'btn-outline'" @click="act('waf.toggle', { bindingid: row.id, ipaddress: row.ip })" x-text="row.enabled ? @js($t['on']) : @js($t['off'])"></button></td>
                </tr></template></tbody>
            </table>
            <p class="vm-note" x-show="!items.length">{{ $t['empty'] }}</p>
        </div>

        {{-- Tasks --}}
        <div x-show="tab === 'tasks' && !loading">
            <table class="vm-table" x-show="items.length">
                <thead><tr><th>{{ $t['task'] }}</th><th>{{ $t['state'] }}</th><th>%</th><th>{{ $t['date'] }}</th></tr></thead>
                <tbody><template x-for="k in items" :key="k.id"><tr>
                    <td><b x-text="k.type"></b><br><small class="text-muted" x-text="k.message || ''"></small></td>
                    <td x-text="k.status"></td><td x-text="k.percentage ?? ''"></td><td x-text="k.updated_at || k.created_at"></td>
                </tr></template></tbody>
            </table>
            <p class="vm-note" x-show="!items.length">{{ $t['empty'] }}</p>
        </div>
    </div>
</div>

<script>
function voyarManage(sectionUrl, actUrl, csrf, t) {
    return {
        tab: null, loading: false, items: [], data: {}, points: [], frame: 'hour', msg: '', msgOk: true,
        open(tab) {
            if (tab !== this.tab) this.msg = '';
            this.tab = tab; this.loading = true;
            var url = sectionUrl.replace('__S__', tab) + (tab === 'graphs' ? '?timeframe=' + this.frame : '');
            fetch(url, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
                this.loading = false;
                if (!j.ok) { this.items = []; this.data = {}; this.points = []; this.say(j.message || t.failed, false); return; }
                this.items = j.items || []; this.data = j; this.points = j.points || [];
            }).catch(() => { this.loading = false; this.say(t.failed, false); });
        },
        act(action, body, confirmText) {
            if (confirmText && !confirm(confirmText)) return;
            fetch(actUrl.replace('__A__', action), {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body || {})
            }).then(r => r.json()).then(j => { this.say(j.message || (j.ok ? t.done : t.failed), j.ok); if (j.ok) setTimeout(() => this.open(this.tab), 800); })
              .catch(() => this.say(t.failed, false));
        },
        say(text, ok) { this.msg = text; this.msgOk = ok; clearTimeout(this._m); this._m = setTimeout(() => this.msg = '', 6000); },
        date(ts) { return ts ? new Date(ts * 1000).toLocaleString() : ''; },
        size(b) { if (!b) return '—'; var u = ['B','KB','MB','GB','TB'], i = 0; while (b >= 1024 && i < 4) { b /= 1024; i++; } return b.toFixed(1) + ' ' + u[i]; },
        wafRows() {
            var rows = [];
            (this.items || []).forEach(b => Object.entries(b.ipaddresses || {}).forEach(([ip, v]) => rows.push({ key: b.bindingid + ip, id: b.bindingid, name: b.name, description: b.description, ip: ip, enabled: !!(v && v.enabled) })));
            return rows;
        },
        chart() {
            var p = this.points; if (!p.length) return '';
            var line = (key, color) => {
                var pts = p.map((d, i) => [p.length > 1 ? i / (p.length - 1) * 600 : 0, 175 - Math.min(100, Math.max(0, d[key] || 0)) * 1.7]);
                return '<polyline fill="none" stroke="' + color + '" stroke-width="2" vector-effect="non-scaling-stroke" points="' + pts.map(x => x.join(',')).join(' ') + '"/>';
            };
            return line('cpu', '#5b4cf0') + (p.some(d => d.mem !== null) ? line('mem', '#12805c') : '');
        },
    };
}
</script>

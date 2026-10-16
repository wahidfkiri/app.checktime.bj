@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Notifications planifiées</h3>
                        <p class="text-subtitle text-muted">Rapports automatiques de ce client (fréquence, destinataires, exécution)</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-body">
                        @forelse($jobs as $job)
                        <div class="border rounded p-3 mb-3 job-row" data-id="{{ $job->id }}" data-command="{{ $job->command }}" data-supports-recipients="{{ $job->supports_recipients ? 1 : 0 }}">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input job-active" type="checkbox" @checked($job->is_active)>
                                        <label class="form-check-label fw-bold">{{ $job->label }}</label>
                                    </div>
                                    <small class="text-muted d-block">{{ $job->scheduleLabel() }}</small>
                                    @if($job->last_run_at)
                                        <small class="text-muted">Dernière exécution : {{ $job->last_run_at->format('d/m/Y H:i') }} — {{ $job->last_status }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary run-btn">
                                    <i class="bi bi-play-circle me-1"></i> Exécuter maintenant
                                </button>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Fréquence</label>
                                    <select class="form-control form-control-sm job-frequency">
                                        @foreach(['daily'=>'Quotidienne','weekly'=>'Hebdomadaire','monthly'=>'Mensuelle','once'=>'Une seule fois','custom'=>'Cron personnalisé'] as $v=>$lbl)
                                            <option value="{{ $v }}" @selected($job->frequency===$v)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 freq-time">
                                    <label class="form-label small">Heure</label>
                                    <input type="time" class="form-control form-control-sm job-time" value="{{ $job->time ?: '09:00' }}">
                                </div>
                                <div class="col-md-3 freq-weekly">
                                    <label class="form-label small">Jour (semaine)</label>
                                    <select class="form-control form-control-sm job-dow">
                                        @foreach([1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'] as $v=>$lbl)
                                            <option value="{{ $v }}" @selected((int)$job->day_of_week===$v)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 freq-monthly">
                                    <label class="form-label small">Jour (mois)</label>
                                    <input type="number" min="1" max="31" class="form-control form-control-sm job-dom" value="{{ $job->day_of_month ?: 1 }}">
                                </div>
                                <div class="col-md-4 freq-once">
                                    <label class="form-label small">Date & heure</label>
                                    <input type="datetime-local" class="form-control form-control-sm job-runat" value="{{ $job->run_at ? $job->run_at->format('Y-m-d\TH:i') : '' }}">
                                </div>
                                <div class="col-md-5 freq-custom">
                                    <label class="form-label small">Expression cron</label>
                                    <input type="text" class="form-control form-control-sm job-cron" placeholder="* * * * *" value="{{ $job->cron_expression }}">
                                </div>
                                @if($job->supports_recipients)
                                <div class="col-md-12 mt-2">
                                    <label class="form-label small">Destinataires (emails séparés par des virgules)</label>
                                    <input type="text" class="form-control form-control-sm job-recipients" value="{{ is_array($job->recipients) ? implode(', ', $job->recipients) : '' }}">
                                </div>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-muted text-center py-3">Aucune tâche planifiée. (Elles sont créées automatiquement par client.)</p>
                        @endforelse

                        @if($jobs->count())
                        <div class="text-end">
                            <button type="button" class="btn btn-primary" id="save-jobs"><i class="bi bi-save me-1"></i> Enregistrer</button>
                        </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    var CSRF = "{{ csrf_token() }}";
    var R = { jobs: "{{ route('notifications.jobs') }}", run: "{{ route('notifications.jobs.run') }}" };
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3500,timerProgressBar:true}); }

    function refreshFreqUI($row){
        var f = $row.find('.job-frequency').val();
        $row.find('.freq-time').toggle(f !== 'once' && f !== 'custom');
        $row.find('.freq-weekly').toggle(f === 'weekly');
        $row.find('.freq-monthly').toggle(f === 'monthly');
        $row.find('.freq-once').toggle(f === 'once');
        $row.find('.freq-custom').toggle(f === 'custom');
    }
    $('.job-row').each(function(){ refreshFreqUI($(this)); });
    $(document).on('change', '.job-frequency', function(){ refreshFreqUI($(this).closest('.job-row')); });

    function collect(){
        return $('.job-row').map(function(){
            var $r = $(this);
            var recips = [];
            if ($r.data('supports-recipients') == 1) {
                recips = ($r.find('.job-recipients').val()||'').split(',').map(s=>s.trim()).filter(Boolean);
            }
            return {
                id: $r.data('id'),
                is_active: $r.find('.job-active').is(':checked') ? 1 : 0,
                frequency: $r.find('.job-frequency').val(),
                time: $r.find('.job-time').val() || '09:00',
                day_of_week: $r.find('.job-dow').val(),
                day_of_month: $r.find('.job-dom').val(),
                cron_expression: $r.find('.job-cron').val(),
                run_at: $r.find('.job-runat').val(),
                recipients: recips
            };
        }).get();
    }

    $('#save-jobs').on('click', function(){
        $.ajax({ url:R.jobs, type:'POST', data:{_token:CSRF, jobs:collect()},
            success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),800);} else { toast('error', r.message||'Erreur'); } },
            error:function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
        });
    });

    $(document).on('click', '.run-btn', function(){
        var command = $(this).closest('.job-row').data('command');
        Swal.fire({title:'Exécuter maintenant ?', text:command, icon:'question', showCancelButton:true, confirmButtonText:'Exécuter', cancelButtonText:'Annuler'})
        .then(function(res){
            if(!res.isConfirmed) return;
            Swal.fire({title:'Exécution en cours...', didOpen:()=>Swal.showLoading(), allowOutsideClick:false});
            $.ajax({ url:R.run, type:'POST', data:{_token:CSRF, command:command},
                success:function(r){ Swal.close(); toast(r.success?'success':'error', r.message); },
                error:function(xhr){ Swal.close(); toast('error', xhr.responseJSON?.message || 'Erreur'); }
            });
        });
    });
});
</script>
@endsection

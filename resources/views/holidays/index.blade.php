@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Jours fériés</h3>
                        <p class="text-subtitle text-muted">Gestion des jours fériés du client</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Année</label>
                                <select class="form-control" id="year_filter">
                                    <option value="">Toutes</option>
                                    @foreach($years as $y)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-control" id="recurring_filter">
                                    <option value="">Tous</option>
                                    <option value="1">Récurrent</option>
                                    <option value="0">Ponctuel</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jour</label>
                                <select class="form-control" id="working_day_filter">
                                    <option value="">Tous</option>
                                    <option value="0">Non travaillé</option>
                                    <option value="1">Travaillé</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="button" class="btn btn-success" id="create-btn">
                                    <i class="bi bi-plus-circle me-1"></i> Nouveau jour férié
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="holidays-table" class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Nom</th>
                                        <th>Type</th>
                                        <th>Jour</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="holidayModalLabel">Nouveau jour férié</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="holidayForm">
                <input type="hidden" id="holiday_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="holiday_date" name="holiday_date" required>
                        <div class="invalid-feedback" id="holiday_date-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="255" required>
                        <div class="invalid-feedback" id="name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring">
                        <label class="form-check-label" for="is_recurring">Récurrent (chaque année)</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_working_day" name="is_working_day">
                        <label class="form-check-label" for="is_working_day">Jour travaillé (férié mais pointé)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    var CSRF = "{{ csrf_token() }}";
    var BASE = "{{ url('holidays') }}";
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3000,timerProgressBar:true}); }

    var table = $('#holidays-table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: "{{ route('holidays.datatable') }}",
            data: function (d) {
                d.year_filter = $('#year_filter').val();
                d.recurring_filter = $('#recurring_filter').val();
                d.working_day_filter = $('#working_day_filter').val();
            }
        },
        columns: [
            { data: 'date_formatted', name: 'holiday_date' },
            { data: 'name', name: 'name' },
            { data: 'recurring_badge', name: 'is_recurring', orderable:false },
            { data: 'working_day_badge', name: 'is_working_day', orderable:false },
            { data: 'actions', name: 'actions', orderable:false, searchable:false }
        ],
        order: [[0,'desc']],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json" }
    });

    $('#year_filter, #recurring_filter, #working_day_filter').on('change', function(){ table.ajax.reload(); });

    function clearErrors(){ $('.invalid-feedback').text(''); $('.form-control').removeClass('is-invalid'); }

    $('#create-btn').on('click', function(){
        clearErrors(); $('#holidayForm')[0].reset(); $('#holiday_id').val('');
        $('#holidayModalLabel').text('Nouveau jour férié');
        new bootstrap.Modal('#holidayModal').show();
    });

    $(document).on('click', '.edit-btn', function(){
        var id = $(this).data('id');
        $.get(BASE + '/' + id + '/edit', function(r){
            if(!r.success){ toast('error', r.message||'Erreur'); return; }
            clearErrors();
            $('#holiday_id').val(r.data.id);
            $('#holiday_date').val(r.data.holiday_date);
            $('#name').val(r.data.name);
            $('#description').val(r.data.description);
            $('#is_recurring').prop('checked', !!r.data.is_recurring);
            $('#is_working_day').prop('checked', !!r.data.is_working_day);
            $('#holidayModalLabel').text('Modifier le jour férié');
            new bootstrap.Modal('#holidayModal').show();
        }).fail(function(){ toast('error','Jour férié non trouvé'); });
    });

    $('#holidayForm').on('submit', function(e){
        e.preventDefault(); clearErrors();
        var id = $('#holiday_id').val();
        var payload = {
            _token: CSRF,
            holiday_date: $('#holiday_date').val(),
            name: $('#name').val(),
            description: $('#description').val(),
            is_recurring: $('#is_recurring').is(':checked') ? 1 : 0,
            is_working_day: $('#is_working_day').is(':checked') ? 1 : 0
        };
        var url = BASE + (id ? '/' + id : '');
        if (id) payload._method = 'PUT';
        $.ajax({ url:url, type:'POST', data:payload,
            success:function(r){ if(r.success){ bootstrap.Modal.getInstance(document.getElementById('holidayModal')).hide(); table.ajax.reload(); toast('success', r.message);} else { toast('error', r.message||'Erreur'); } },
            error:function(xhr){
                if(xhr.status===422 && xhr.responseJSON?.errors){
                    Object.keys(xhr.responseJSON.errors).forEach(function(k){ $('#'+k+'-error').text(xhr.responseJSON.errors[k][0]); $('#'+k).addClass('is-invalid'); });
                } else { toast('error', xhr.responseJSON?.message || 'Erreur'); }
            }
        });
    });

    $(document).on('click', '.delete-btn', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        Swal.fire({ title:'Supprimer ?', html:'Jour férié : <strong>'+name+'</strong>', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Supprimer', cancelButtonText:'Annuler' })
        .then(function(res){
            if(!res.isConfirmed) return;
            $.ajax({ url: BASE + '/' + id, type:'POST', data:{_token:CSRF, _method:'DELETE'},
                success:function(r){ if(r.success){ table.ajax.reload(); toast('success', r.message);} else { toast('error', r.message||'Erreur'); } },
                error:function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
            });
        });
    });
});
</script>
@endsection

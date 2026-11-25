import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import Modal from '../../components/Modal';
import StatusBadge from '../../components/StatusBadge';
import {
  Users,
  Search,
  Filter,
  Plus,
  KeyRound,
  Compass,
  Download,
  CheckCircle,
  FileText,
  UserCheck,
  UserX,
  Building
} from 'lucide-react';

export default function StudentsManagement() {
  const [registrations, setRegistrations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [exams, setExams] = useState([]);
  const [centers, setCenters] = useState([]);
  const [zones, setZones] = useState([]);

  // Filters
  const [search, setSearch] = useState('');
  const [selectedExamId, setSelectedExamId] = useState('');
  const [selectedCenterId, setSelectedCenterId] = useState('');
  const [evaluationStatus, setEvaluationStatus] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // Modals
  const [isManualModalOpen, setIsManualModalOpen] = useState(false);
  const [isSeatModalOpen, setIsSeatModalOpen] = useState(false);
  const [activeRegForSeat, setActiveRegForSeat] = useState(null);
  const [seatFormData, setSeatFormData] = useState({ center_id: '', room_id: '', seat_number: '' });
  const [centerRooms, setCenterRooms] = useState([]);

  const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);
  const [generatedPasswordData, setGeneratedPasswordData] = useState(null);

  // Manual Form
  const [manualForm, setManualForm] = useState({
    name: '',
    phone: '',
    email: '',
    exam_id: '',
    center_id: '',
    institution: '',
    hsc_batch: 'HSC-2026',
    seat_number: '',
  });

  useEffect(() => {
    fetchExams();
    fetchCenters();
    fetchZones();
  }, []);

  useEffect(() => {
    fetchStudents();
  }, [search, selectedExamId, selectedCenterId, evaluationStatus, page]);

  const fetchExams = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success) {
        setExams(res.data.data.data);
        if (res.data.data.data.length > 0 && !selectedExamId) {
          setSelectedExamId(res.data.data.data[0].id);
        }
      }
    } catch (e) {}
  };

  const fetchCenters = async () => {
    try {
      const res = await api.get('/admin/centers');
      if (res.data.success) setCenters(res.data.data);
    } catch (e) {}
  };

  const fetchZones = async () => {
    try {
      const res = await api.get('/admin/zones');
      if (res.data.success) setZones(res.data.data);
    } catch (e) {}
  };

  const fetchStudents = async () => {
    setLoading(true);
    try {
      let url = `/admin/students?page=${page}&per_page=20`;
      if (search) url += `&search=${encodeURIComponent(search)}`;
      if (selectedExamId) url += `&exam_id=${selectedExamId}`;
      if (selectedCenterId) url += `&center_id=${selectedCenterId}`;
      if (evaluationStatus) url += `&evaluation_status=${evaluationStatus}`;

      const res = await api.get(url);
      if (res.data.success) {
        setRegistrations(res.data.data.data);
        setTotalPages(res.data.data.last_page);
      }
    } catch (e) {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const handleOpenManual = () => {
    setManualForm({
      name: '',
      phone: '',
      email: '',
      exam_id: selectedExamId || exams[0]?.id || '',
      center_id: centers[0]?.id || '',
      institution: '',
      hsc_batch: 'HSC-2026',
      seat_number: '',
    });
    setIsManualModalOpen(true);
  };

  const handleManualSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await api.post('/admin/students', manualForm);
      if (res.data.success) {
        setIsManualModalOpen(false);
        if (res.data.data.temporary_password) {
          setGeneratedPasswordData({
            name: manualForm.name,
            regNo: res.data.data.registration.student.registration_number,
            password: res.data.data.temporary_password,
          });
          setIsPasswordModalOpen(true);
        }
        fetchStudents();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error registering student.');
    }
  };

  const handleOpenSeatModal = async (reg) => {
    setActiveRegForSeat(reg);
    setSeatFormData({
      center_id: reg.center_id || '',
      room_id: reg.room_id || '',
      seat_number: reg.seat_number || '',
    });

    if (reg.center_id) {
      try {
        const res = await api.get(`/admin/centers/${reg.center_id}/rooms`);
        if (res.data.success) setCenterRooms(res.data.data);
      } catch (e) {
        setCenterRooms([]);
      }
    }
    setIsSeatModalOpen(true);
  };

  const handleCenterChangeForSeat = async (centerId) => {
    setSeatFormData({ ...seatFormData, center_id: centerId, room_id: '' });
    try {
      const res = await api.get(`/admin/centers/${centerId}/rooms`);
      if (res.data.success) setCenterRooms(res.data.data);
    } catch (e) {
      setCenterRooms([]);
    }
  };

  const handleSaveSeat = async (e) => {
    e.preventDefault();
    if (!activeRegForSeat) return;
    try {
      await api.post(`/admin/students/${activeRegForSeat.id}/reassign-seat`, seatFormData);
      setIsSeatModalOpen(false);
      fetchStudents();
    } catch (err) {
      alert(err.response?.data?.message || 'Error reassigning seat.');
    }
  };

  const handleResetPassword = async (reg) => {
    if (!confirm(`Reset temporary password for ${reg.student.user.name}?`)) return;
    try {
      const res = await api.post(`/admin/students/${reg.id}/reset-password`);
      if (res.data.success) {
        setGeneratedPasswordData({
          name: reg.student.user.name,
          regNo: res.data.data.registration_number,
          password: res.data.data.new_temporary_password,
        });
        setIsPasswordModalOpen(true);
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error resetting password.');
    }
  };

  const handleToggleStatus = async (reg) => {
    try {
      await api.patch(`/admin/students/${reg.id}/toggle-status`);
      fetchStudents();
    } catch (err) {
      alert('Error updating status.');
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Student Registry & Seating</h1>
          <p className="text-xs text-slate-500 mt-1">Manage enrolled candidates, seat allocations, credentials, and verification status.</p>
        </div>

        <button
          onClick={handleOpenManual}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" /> Enroll Individual Student
        </button>
      </div>

      {/* Filter Toolbar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <div className="relative col-span-1 sm:col-span-2">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
            <input
              type="text"
              placeholder="Search by Name, Reg No (17...), Phone, or Institution..."
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
            />
          </div>

          <div>
            <select
              value={selectedExamId}
              onChange={(e) => { setSelectedExamId(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 text-xs font-semibold text-slate-700 border border-slate-200 rounded-xl focus:outline-hidden bg-white"
            >
              <option value="">All Examinations</option>
              {exams.map(e => (
                <option key={e.id} value={e.id}>{e.code} - {e.title}</option>
              ))}
            </select>
          </div>

          <div>
            <select
              value={selectedCenterId}
              onChange={(e) => { setSelectedCenterId(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 text-xs font-semibold text-slate-700 border border-slate-200 rounded-xl focus:outline-hidden bg-white"
            >
              <option value="">All Centers</option>
              {centers.map(c => (
                <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
              ))}
            </select>
          </div>
        </div>

        <div className="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
          <div className="flex items-center gap-2">
            <span className="text-[11px] font-bold text-slate-500 uppercase">Evaluation:</span>
            <button
              onClick={() => { setEvaluationStatus(''); setPage(1); }}
              className={`px-2.5 py-1 rounded-lg text-xs font-bold ${evaluationStatus === '' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}`}
            >
              All
            </button>
            <button
              onClick={() => { setEvaluationStatus('evaluated'); setPage(1); }}
              className={`px-2.5 py-1 rounded-lg text-xs font-bold ${evaluationStatus === 'evaluated' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'}`}
            >
              Evaluated
            </button>
            <button
              onClick={() => { setEvaluationStatus('pending'); setPage(1); }}
              className={`px-2.5 py-1 rounded-lg text-xs font-bold ${evaluationStatus === 'pending' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600'}`}
            >
              Pending Marks
            </button>
          </div>
        </div>
      </div>

      {/* Registry Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                <th className="py-3 px-4">Candidate Details</th>
                <th className="py-3 px-4">Examination</th>
                <th className="py-3 px-4">Assigned Center & Seat</th>
                <th className="py-3 px-4">Admit Card</th>
                <th className="py-3 px-4">Evaluation / Result</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">Loading student registry...</td>
                </tr>
              ) : registrations.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">No student registrations found matching filters.</td>
                </tr>
              ) : (
                registrations.map((reg) => (
                  <tr key={reg.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3.5 px-4">
                      <div className="font-bold text-slate-900 text-sm">{reg.student?.user?.name}</div>
                      <div className="text-[11px] font-mono font-bold text-blue-600">
                        Reg: {reg.student?.registration_number} &bull; {reg.student?.canonical_phone}
                      </div>
                      <div className="text-[10px] text-slate-400">{reg.student?.institution || 'General Candidate'} ({reg.student?.hsc_batch || 'HSC'})</div>
                    </td>

                    <td className="py-3.5 px-4">
                      <div className="font-semibold text-slate-800">{reg.exam?.title}</div>
                      <span className="text-[10px] font-mono font-bold text-slate-400">{reg.exam?.code}</span>
                    </td>

                    <td className="py-3.5 px-4">
                      {reg.center ? (
                        <div>
                          <div className="font-bold text-slate-800">{reg.center.name}</div>
                          <div className="text-[11px] text-slate-500">
                            {reg.room?.room_number || 'Room TBD'} &bull; Seat: <strong className="text-slate-900 font-black">{reg.seat_number || 'Auto'}</strong>
                          </div>
                        </div>
                      ) : (
                        <span className="text-amber-600 font-bold text-[11px] bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                          Unassigned Center
                        </span>
                      )}
                    </td>

                    <td className="py-3.5 px-4">
                      {reg.admit_card_downloaded_count > 0 ? (
                        <div className="text-emerald-600 text-[11px] font-bold flex items-center gap-1">
                          <CheckCircle className="w-3.5 h-3.5" /> Downloaded ({reg.admit_card_downloaded_count}x)
                        </div>
                      ) : (
                        <span className="text-slate-400 text-[11px]">Not downloaded</span>
                      )}
                    </td>

                    <td className="py-3.5 px-4">
                      {reg.marks ? (
                        <div>
                          <span className="font-black text-emerald-600 text-sm">{reg.marks.final_marks}</span>
                          <span className="text-slate-400 text-[10px]"> / {reg.exam?.total_marks}</span>
                          <div className="text-[10px] text-slate-500">By: {reg.marks.evaluator?.name}</div>
                        </div>
                      ) : (
                        <span className="text-amber-600 font-semibold text-[11px] bg-amber-50 px-2 py-0.5 rounded-md">
                          Unevaluated
                        </span>
                      )}
                    </td>

                    <td className="py-3.5 px-4 text-right">
                      <div className="inline-flex items-center gap-1.5">
                        <button
                          onClick={() => handleOpenSeatModal(reg)}
                          title="Reassign Examination Center or Seat"
                          className="p-1.5 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        >
                          <Compass className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleResetPassword(reg)}
                          title="Reset Temporary Password"
                          className="p-1.5 text-slate-600 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                        >
                          <KeyRound className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleToggleStatus(reg)}
                          title={reg.student?.user?.is_active ? 'Deactivate Account' : 'Activate Account'}
                          className={`p-1.5 rounded-lg transition-colors ${reg.student?.user?.is_active ? 'text-slate-600 hover:text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'}`}
                        >
                          {reg.student?.user?.is_active ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs">
            <span className="text-slate-500">Page {page} of {totalPages}</span>
            <div className="flex gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage(p => p - 1)}
                className="px-3 py-1.5 border border-slate-200 rounded-lg disabled:opacity-40"
              >
                Previous
              </button>
              <button
                disabled={page >= totalPages}
                onClick={() => setPage(p => p + 1)}
                className="px-3 py-1.5 border border-slate-200 rounded-lg disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Reassign Seat Modal */}
      <Modal
        isOpen={isSeatModalOpen}
        onClose={() => setIsSeatModalOpen(false)}
        title={`Reassign Center & Seat: ${activeRegForSeat?.student?.user?.name}`}
      >
        <form onSubmit={handleSaveSeat} className="space-y-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Examination Center *
            </label>
            <select
              required
              value={seatFormData.center_id}
              onChange={(e) => handleCenterChangeForSeat(e.target.value)}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            >
              <option value="">Select Examination Center</option>
              {centers.map(c => (
                <option key={c.id} value={c.id}>{c.name} ({c.code}) - {c.zone?.name}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Examination Room
              </label>
              <select
                value={seatFormData.room_id}
                onChange={(e) => setSeatFormData({ ...seatFormData, room_id: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              >
                <option value="">Auto-Assign / Hall</option>
                {centerRooms.map(r => (
                  <option key={r.id} value={r.id}>{r.room_number} ({r.floor})</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Seat Number
              </label>
              <input
                type="text"
                placeholder="e.g. A-14"
                value={seatFormData.seat_number}
                onChange={(e) => setSeatFormData({ ...seatFormData, seat_number: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsSeatModalOpen(false)}
              className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
            >
              Save Allocation
            </button>
          </div>
        </form>
      </Modal>

      {/* Manual Registration Modal */}
      <Modal
        isOpen={isManualModalOpen}
        onClose={() => setIsManualModalOpen(false)}
        title="Enroll Individual Candidate"
      >
        <form onSubmit={handleManualSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Examination *
            </label>
            <select
              required
              value={manualForm.exam_id}
              onChange={(e) => setManualForm({ ...manualForm, exam_id: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            >
              {exams.map(e => (
                <option key={e.id} value={e.id}>{e.code} - {e.title}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Student Full Name *
              </label>
              <input
                type="text"
                required
                placeholder="Full Name"
                value={manualForm.name}
                onChange={(e) => setManualForm({ ...manualForm, name: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Bangladeshi Mobile No *
              </label>
              <input
                type="text"
                required
                placeholder="01712345678"
                value={manualForm.phone}
                onChange={(e) => setManualForm({ ...manualForm, phone: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Institution / College
              </label>
              <input
                type="text"
                placeholder="e.g. Notre Dame College"
                value={manualForm.institution}
                onChange={(e) => setManualForm({ ...manualForm, institution: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                HSC Batch
              </label>
              <input
                type="text"
                placeholder="HSC-2026"
                value={manualForm.hsc_batch}
                onChange={(e) => setManualForm({ ...manualForm, hsc_batch: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Examination Center
              </label>
              <select
                value={manualForm.center_id}
                onChange={(e) => setManualForm({ ...manualForm, center_id: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              >
                <option value="">Select Center</option>
                {centers.map(c => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Seat Number (Optional)
              </label>
              <input
                type="text"
                placeholder="e.g. A-12"
                value={manualForm.seat_number}
                onChange={(e) => setManualForm({ ...manualForm, seat_number: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsManualModalOpen(false)}
              className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
            >
              Enroll Student
            </button>
          </div>
        </form>
      </Modal>

      {/* Generated Credentials Popup */}
      <Modal
        isOpen={isPasswordModalOpen}
        onClose={() => setIsPasswordModalOpen(false)}
        title="Student Credentials Generated"
      >
        <div className="space-y-4">
          <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs">
            <p className="font-bold">Student Account Provisioned Successfully!</p>
            <p className="mt-1">Provide these credentials to the candidate. They will be prompted to change their password on first login.</p>
          </div>

          <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
            <div className="flex justify-between">
              <span className="text-slate-500">Student Name:</span>
              <strong className="text-slate-900">{generatedPasswordData?.name}</strong>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Registration Number (Username):</span>
              <strong className="text-blue-600 font-mono text-sm">{generatedPasswordData?.regNo}</strong>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Temporary Password:</span>
              <strong className="text-rose-600 font-mono text-sm">{generatedPasswordData?.password}</strong>
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="button"
              onClick={() => setIsPasswordModalOpen(false)}
              className="px-5 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase tracking-wider"
            >
              Done
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

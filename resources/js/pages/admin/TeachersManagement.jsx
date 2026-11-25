import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import Modal from '../../components/Modal';
import {
  GraduationCap,
  Plus,
  Search,
  KeyRound,
  CalendarCheck,
  CheckCircle,
  UserX,
  UserCheck,
  Edit,
  Phone,
  Mail,
  Award
} from 'lucide-react';

export default function TeachersManagement() {
  const [teachers, setTeachers] = useState([]);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  // Modals
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
  const [activeTeacherForAssign, setActiveTeacherForAssign] = useState(null);
  const [selectedExamIds, setSelectedExamIds] = useState([]);

  // Teacher Form
  const [form, setForm] = useState({
    name: '',
    username: '',
    phone: '',
    email: '',
    password: '',
    designation: 'Senior Faculty',
    institution: '',
    department: 'Biology',
  });

  useEffect(() => {
    fetchExams();
    fetchTeachers();
  }, [search]);

  const fetchExams = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success) setExams(res.data.data.data);
    } catch (e) {}
  };

  const fetchTeachers = async () => {
    setLoading(true);
    try {
      let url = '/admin/teachers?';
      if (search) url += `search=${encodeURIComponent(search)}&`;
      const res = await api.get(url);
      if (res.data.success) setTeachers(res.data.data.data);
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  const handleOpenAdd = () => {
    setForm({
      name: '',
      username: '',
      phone: '',
      email: '',
      password: '',
      designation: 'Senior Faculty',
      institution: 'Medical College',
      department: 'Biology',
    });
    setIsAddModalOpen(true);
  };

  const handleAddSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await api.post('/admin/teachers', form);
      if (res.data.success) {
        setIsAddModalOpen(false);
        fetchTeachers();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error creating teacher account.');
    }
  };

  const handleOpenAssign = (teacher) => {
    setActiveTeacherForAssign(teacher);
    const assignedIds = (teacher.teacher_assignments || []).map(a => a.exam_id);
    setSelectedExamIds(assignedIds);
    setIsAssignModalOpen(true);
  };

  const handleSaveAssignments = async () => {
    if (!activeTeacherForAssign) return;
    try {
      await api.post(`/admin/teachers/${activeTeacherForAssign.id}/assign-exams`, {
        exam_ids: selectedExamIds,
      });
      setIsAssignModalOpen(false);
      fetchTeachers();
    } catch (err) {
      alert(err.response?.data?.message || 'Error assigning examinations.');
    }
  };

  const handleToggleStatus = async (teacher) => {
    try {
      await api.patch(`/admin/teachers/${teacher.id}/toggle-status`);
      fetchTeachers();
    } catch (err) {
      alert('Error updating teacher status.');
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Faculty & Evaluator Roster</h1>
          <p className="text-xs text-slate-500 mt-1">Manage teacher accounts, assign examination evaluation decks, and view grading progress.</p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" /> Add Evaluator Account
        </button>
      </div>

      {/* Search */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
          <input
            type="text"
            placeholder="Search faculty by name, username, or phone..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
          />
        </div>
      </div>

      {/* Teachers Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {loading ? (
          <div className="col-span-full py-12 text-center text-xs text-slate-400">Loading faculty accounts...</div>
        ) : teachers.length === 0 ? (
          <div className="col-span-full py-12 text-center text-xs text-slate-400">No evaluators found.</div>
        ) : (
          teachers.map(teacher => (
            <div key={teacher.id} className="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between hover:border-blue-300 transition-all">
              <div>
                <div className="flex items-start justify-between gap-2 mb-2">
                  <div>
                    <h3 className="text-base font-bold text-slate-900">{teacher.name}</h3>
                    <div className="text-[11px] text-blue-600 font-semibold">{teacher.teacher_profile?.designation || 'Faculty'}</div>
                  </div>
                  <span className="font-mono text-xs font-bold text-slate-400">@{teacher.username}</span>
                </div>

                <div className="text-xs text-slate-500 mb-4 space-y-1">
                  <div className="flex items-center gap-1.5"><Phone className="w-3.5 h-3.5 text-slate-400" /> {teacher.phone}</div>
                  {teacher.email && <div className="flex items-center gap-1.5"><Mail className="w-3.5 h-3.5 text-slate-400" /> {teacher.email}</div>}
                  <div className="text-[11px] text-slate-600 font-medium">{teacher.teacher_profile?.institution}</div>
                </div>

                <div className="bg-slate-50 p-3 rounded-xl border border-slate-100 mb-4 text-xs">
                  <span className="text-[10px] uppercase font-bold text-slate-400 block mb-1">Assigned Examinations</span>
                  <div className="flex flex-wrap gap-1">
                    {(teacher.teacher_assignments || []).length === 0 ? (
                      <span className="text-slate-400 text-[11px]">No active assignments</span>
                    ) : (
                      teacher.teacher_assignments.map(a => (
                        <span key={a.id} className="bg-white px-2 py-0.5 rounded-md border border-slate-200 text-[10px] font-bold text-slate-700">
                          {a.exam?.code}
                        </span>
                      ))
                    )}
                  </div>
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <button
                  onClick={() => handleOpenAssign(teacher)}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800"
                >
                  <CalendarCheck className="w-3.5 h-3.5" /> Assign Exams
                </button>

                <button
                  onClick={() => handleToggleStatus(teacher)}
                  title={teacher.is_active ? 'Deactivate' : 'Activate'}
                  className={`p-1.5 rounded-lg transition-colors ${teacher.is_active ? 'text-slate-500 hover:text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50'}`}
                >
                  {teacher.is_active ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Add Teacher Modal */}
      <Modal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        title="Add New Evaluator Account"
      >
        <form onSubmit={handleAddSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Full Name *
              </label>
              <input
                type="text"
                required
                placeholder="Dr. Mahbubur Rahman"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Username *
              </label>
              <input
                type="text"
                required
                placeholder="teacher3"
                value={form.username}
                onChange={(e) => setForm({ ...form, username: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Mobile Number *
              </label>
              <input
                type="text"
                required
                placeholder="01711223344"
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Password *
              </label>
              <input
                type="password"
                required
                placeholder="••••••••"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Designation
              </label>
              <input
                type="text"
                placeholder="e.g. Associate Professor"
                value={form.designation}
                onChange={(e) => setForm({ ...form, designation: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Institution
              </label>
              <input
                type="text"
                placeholder="e.g. Dhaka Medical College"
                value={form.institution}
                onChange={(e) => setForm({ ...form, institution: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsAddModalOpen(false)}
              className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
            >
              Create Account
            </button>
          </div>
        </form>
      </Modal>

      {/* Assign Exams Modal */}
      <Modal
        isOpen={isAssignModalOpen}
        onClose={() => setIsAssignModalOpen(false)}
        title={`Assign Examinations: ${activeTeacherForAssign?.name}`}
      >
        <div className="space-y-4">
          <p className="text-xs text-slate-500">
            Select examinations this teacher is authorized to evaluate answer scripts for.
          </p>

          <div className="max-h-64 overflow-y-auto space-y-2 border border-slate-100 p-2 rounded-xl">
            {exams.map(exam => {
              const isChecked = selectedExamIds.includes(exam.id);
              return (
                <label
                  key={exam.id}
                  className={`flex items-center justify-between p-3 rounded-xl border text-xs cursor-pointer transition-colors ${isChecked ? 'bg-blue-50/60 border-blue-200' : 'bg-white border-slate-200 hover:bg-slate-50'}`}
                >
                  <div className="flex items-center gap-3">
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={(e) => {
                        if (e.target.checked) setSelectedExamIds([...selectedExamIds, exam.id]);
                        else setSelectedExamIds(selectedExamIds.filter(id => id !== exam.id));
                      }}
                      className="w-4 h-4 text-blue-600 rounded-md"
                    />
                    <div>
                      <div className="font-bold text-slate-900">{exam.title}</div>
                      <div className="text-[10px] text-slate-400">{exam.exam_date} &bull; Total: {exam.total_marks} Marks</div>
                    </div>
                  </div>
                  <span className="font-mono text-[10px] font-bold text-slate-400">{exam.code}</span>
                </label>
              );
            })}
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsAssignModalOpen(false)}
              className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleSaveAssignments}
              className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
            >
              Save Assignments
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

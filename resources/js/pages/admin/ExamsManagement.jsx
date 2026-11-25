import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import StatusBadge from '../../components/StatusBadge';
import Modal from '../../components/Modal';
import {
  CalendarCheck,
  Plus,
  Search,
  Building,
  Edit,
  Trash2,
  ChevronRight,
  Clock,
  Layers,
  CheckCircle,
  AlertCircle
} from 'lucide-react';

export default function ExamsManagement() {
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingExam, setEditingExam] = useState(null);
  const [isCentersModalOpen, setIsCentersModalOpen] = useState(false);
  const [activeExamForCenters, setActiveExamForCenters] = useState(null);
  const [availableCenters, setAvailableCenters] = useState([]);
  const [selectedCenterIds, setSelectedCenterIds] = useState([]);

  const [formData, setFormData] = useState({
    title: '',
    code: '',
    description: '',
    exam_date: '',
    start_time: '10:00',
    end_time: '11:00',
    duration_minutes: 60,
    total_marks: 100,
    total_questions: 100,
    marks_per_correct: 1.0,
    negative_marks_per_incorrect: 0.25,
    marks_per_unanswered: 0.0,
    pass_marks: 40,
    instructions: '',
    registration_deadline: '',
    admit_card_publish_at: '',
    status: 'draft',
  });

  useEffect(() => {
    fetchExams();
    fetchCenters();
  }, [search, statusFilter]);

  const fetchExams = async () => {
    setLoading(true);
    try {
      let url = '/admin/exams?per_page=50';
      if (search) url += `&search=${encodeURIComponent(search)}`;
      if (statusFilter) url += `&status=${statusFilter}`;
      const res = await api.get(url);
      if (res.data.success) {
        setExams(res.data.data.data);
      }
    } catch (e) {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const fetchCenters = async () => {
    try {
      const res = await api.get('/admin/centers');
      if (res.data.success) {
        setAvailableCenters(res.data.data);
      }
    } catch (e) {
      // ignore
    }
  };

  const handleOpenCreate = () => {
    setEditingExam(null);
    setFormData({
      title: '',
      code: '',
      description: '',
      exam_date: '',
      start_time: '10:00',
      end_time: '11:00',
      duration_minutes: 60,
      total_marks: 100,
      total_questions: 100,
      marks_per_correct: 1.0,
      negative_marks_per_incorrect: 0.25,
      marks_per_unanswered: 0.0,
      pass_marks: 40,
      instructions: 'Use Black Ballpoint Pen on OMR sheet. No digital watches or phones.',
      registration_deadline: '',
      admit_card_publish_at: '',
      status: 'draft',
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (exam) => {
    setEditingExam(exam);
    setFormData({
      title: exam.title,
      code: exam.code,
      description: exam.description || '',
      exam_date: exam.exam_date,
      start_time: exam.start_time,
      end_time: exam.end_time,
      duration_minutes: exam.duration_minutes,
      total_marks: exam.total_marks,
      total_questions: exam.total_questions,
      marks_per_correct: exam.marks_per_correct,
      negative_marks_per_incorrect: exam.negative_marks_per_incorrect,
      marks_per_unanswered: exam.marks_per_unanswered || 0,
      pass_marks: exam.pass_marks,
      instructions: exam.instructions || '',
      registration_deadline: exam.registration_deadline ? exam.registration_deadline.slice(0, 16) : '',
      admit_card_publish_at: exam.admit_card_publish_at ? exam.admit_card_publish_at.slice(0, 16) : '',
      status: exam.status,
    });
    setIsModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (editingExam) {
        await api.put(`/admin/exams/${editingExam.id}`, formData);
      } else {
        await api.post('/admin/exams', formData);
      }
      setIsModalOpen(false);
      fetchExams();
    } catch (err) {
      alert(err.response?.data?.message || 'Error saving examination.');
    }
  };

  const handleStatusChange = async (examId, newStatus) => {
    try {
      await api.patch(`/admin/exams/${examId}/status`, { status: newStatus });
      fetchExams();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to update status.');
    }
  };

  const handleDeleteExam = async (exam, force = false) => {
    if (!force && !confirm(`Are you sure you want to delete examination "${exam.title}" (${exam.code})?`)) {
      return;
    }

    try {
      const url = force ? `/admin/exams/${exam.id}?force=true` : `/admin/exams/${exam.id}`;
      const res = await api.delete(url);
      if (res.data.success) {
        alert(res.data.message || 'Examination deleted successfully.');
        fetchExams();
      }
    } catch (err) {
      if (err.response?.data?.has_registrations) {
        if (confirm(`${err.response.data.message}\n\nDo you want to FORCE DELETE this examination along with all its ${err.response.data.registrations_count} candidate registrations, marks, and results?\n\nWarning: This action is permanent and cannot be undone.`)) {
          handleDeleteExam(exam, true);
          return;
        }
      } else {
        alert(err.response?.data?.message || 'Error deleting examination.');
      }
    }
  };

  const handleOpenCenters = async (exam) => {
    setActiveExamForCenters(exam);
    try {
      const res = await api.get(`/admin/exams/${exam.id}`);
      if (res.data.success) {
        const assignedIds = (res.data.data.exam_centers || []).map(ec => ec.center_id);
        setSelectedCenterIds(assignedIds);
      }
    } catch (e) {
      setSelectedCenterIds([]);
    }
    setIsCentersModalOpen(true);
  };

  const handleSaveCenters = async () => {
    if (!activeExamForCenters) return;
    try {
      const payload = {
        centers: selectedCenterIds.map(id => ({ center_id: id, allocated_capacity: 0 }))
      };
      await api.post(`/admin/exams/${activeExamForCenters.id}/centers`, payload);
      setIsCentersModalOpen(false);
      fetchExams();
    } catch (err) {
      alert(err.response?.data?.message || 'Error updating exam centers.');
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Examinations Management</h1>
          <p className="text-xs text-slate-500 mt-1">Configure offline exams, marking schemes, deadlines, and center allotments.</p>
        </div>

        <button
          onClick={handleOpenCreate}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
        >
          <Plus className="w-4 h-4" /> Create Examination
        </button>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
          <input
            type="text"
            placeholder="Search exam by title or code..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
          />
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-3 py-2 text-xs font-semibold text-slate-700 border border-slate-200 rounded-xl focus:outline-hidden bg-white w-full sm:w-auto"
          >
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="registration_open">Registration Open</option>
            <option value="registration_closed">Registration Closed</option>
            <option value="admit_card_published">Admit Card Published</option>
            <option value="evaluation_in_progress">Evaluation In Progress</option>
            <option value="evaluation_completed">Evaluation Completed</option>
            <option value="result_published">Result Published</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>

      {/* Examinations Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                <th className="py-3 px-4">Exam Info</th>
                <th className="py-3 px-4">Schedule</th>
                <th className="py-3 px-4">Marking Scheme</th>
                <th className="py-3 px-4">Registrations</th>
                <th className="py-3 px-4">Status & Transition</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">Loading examinations...</td>
                </tr>
              ) : exams.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">No examinations found.</td>
                </tr>
              ) : (
                exams.map((exam) => (
                  <tr key={exam.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="py-3.5 px-4">
                      <div className="font-bold text-slate-900 text-sm">{exam.title}</div>
                      <div className="text-[11px] font-mono text-blue-600 font-bold">{exam.code}</div>
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="font-semibold text-slate-800">{exam.exam_date}</div>
                      <div className="text-[10px] text-slate-400 flex items-center gap-1">
                        <Clock className="w-3 h-3" /> {exam.start_time} - {exam.end_time} ({exam.duration_minutes}m)
                      </div>
                    </td>
                    <td className="py-3.5 px-4">
                      <div>Total: <span className="font-bold text-slate-900">{exam.total_marks} Marks</span> ({exam.total_questions} Qs)</div>
                      <div className="text-[10px] text-slate-500">
                        +{exam.marks_per_correct} / -{exam.negative_marks_per_incorrect} &bull; Pass: {exam.pass_marks}
                      </div>
                    </td>
                    <td className="py-3.5 px-4">
                      <span className="font-bold text-slate-900">{exam.registrations_count || 0}</span>
                      <span className="text-slate-400 text-[10px] block">Students</span>
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="flex flex-col gap-1.5 items-start">
                        <StatusBadge status={exam.status} />
                        <select
                          value={exam.status}
                          onChange={(e) => handleStatusChange(exam.id, e.target.value)}
                          className="text-[10px] font-semibold text-slate-600 border border-slate-200 rounded-md px-1.5 py-0.5 bg-slate-50 focus:outline-hidden"
                        >
                          <option value="draft">Draft</option>
                          <option value="registration_open">Registration Open</option>
                          <option value="registration_closed">Registration Closed</option>
                          <option value="admit_card_published">Admit Published</option>
                          <option value="evaluation_in_progress">Evaluation In Progress</option>
                          <option value="evaluation_completed">Evaluation Completed</option>
                          <option value="result_published">Result Published</option>
                          <option value="archived">Archived</option>
                        </select>
                      </div>
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <div className="inline-flex items-center gap-1.5">
                        <button
                          onClick={() => handleOpenCenters(exam)}
                          title="Assign Examination Centers"
                          className="p-1.5 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                        >
                          <Building className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenEdit(exam)}
                          title="Edit Examination"
                          className="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteExam(exam)}
                          title="Delete Examination"
                          className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Create / Edit Exam Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingExam ? 'Edit Examination' : 'Create New Examination'}
        maxWidth="max-w-2xl"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Exam Title *
              </label>
              <input
                type="text"
                required
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
                placeholder="e.g., Central Grand Mock Test"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Exam Code * (Unique)
              </label>
              <input
                type="text"
                required
                value={formData.code}
                onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
                placeholder="e.g., MED-2026-02"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Exam Date *
              </label>
              <input
                type="date"
                required
                value={formData.exam_date}
                onChange={(e) => setFormData({ ...formData, exam_date: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Start Time *
              </label>
              <input
                type="time"
                required
                value={formData.start_time}
                onChange={(e) => setFormData({ ...formData, start_time: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                End Time *
              </label>
              <input
                type="time"
                required
                value={formData.end_time}
                onChange={(e) => setFormData({ ...formData, end_time: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              />
            </div>
          </div>

          {/* Marking Rules */}
          <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
            <span className="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
              <Layers className="w-3.5 h-3.5 text-blue-600" /> Marking & Question Configuration
            </span>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label className="block text-[10px] font-bold text-slate-600 uppercase">Total Marks</label>
                <input
                  type="number"
                  step="0.01"
                  required
                  value={formData.total_marks}
                  onChange={(e) => setFormData({ ...formData, total_marks: parseFloat(e.target.value) })}
                  className="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-600 uppercase">Total Questions</label>
                <input
                  type="number"
                  required
                  value={formData.total_questions}
                  onChange={(e) => setFormData({ ...formData, total_questions: parseInt(e.target.value) })}
                  className="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-600 uppercase">Marks / Correct</label>
                <input
                  type="number"
                  step="0.01"
                  required
                  value={formData.marks_per_correct}
                  onChange={(e) => setFormData({ ...formData, marks_per_correct: parseFloat(e.target.value) })}
                  className="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-600 uppercase">Penalty / Wrong</label>
                <input
                  type="number"
                  step="0.01"
                  required
                  value={formData.negative_marks_per_incorrect}
                  onChange={(e) => setFormData({ ...formData, negative_marks_per_incorrect: parseFloat(e.target.value) })}
                  className="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
                />
              </div>
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Instructions for Candidates
            </label>
            <textarea
              rows={2}
              value={formData.instructions}
              onChange={(e) => setFormData({ ...formData, instructions: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              placeholder="Admit card and physical examination instructions..."
            />
          </div>

          <div className="flex items-center justify-between pt-3 border-t border-slate-100">
            {editingExam ? (
              <button
                type="button"
                onClick={() => {
                  setIsModalOpen(false);
                  handleDeleteExam(editingExam);
                }}
                className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-xl transition-colors cursor-pointer"
              >
                <Trash2 className="w-3.5 h-3.5" /> Delete Examination
              </button>
            ) : <div />}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setIsModalOpen(false)}
                className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700 transition-colors cursor-pointer"
              >
                {editingExam ? 'Update Examination' : 'Save Examination'}
              </button>
            </div>
          </div>
        </form>
      </Modal>

      {/* Center Allocation Modal */}
      <Modal
        isOpen={isCentersModalOpen}
        onClose={() => setIsCentersModalOpen(false)}
        title={`Assign Examination Centers - ${activeExamForCenters?.code}`}
        maxWidth="max-w-xl"
      >
        <div className="space-y-4">
          <p className="text-xs text-slate-500">
            Select examination centers that will host examinees for this examination.
          </p>

          <div className="max-h-72 overflow-y-auto space-y-2 border border-slate-100 p-2 rounded-xl">
            {availableCenters.map(center => {
              const isChecked = selectedCenterIds.includes(center.id);
              return (
                <label
                  key={center.id}
                  className={`flex items-center justify-between p-3 rounded-xl border text-xs cursor-pointer transition-colors ${isChecked ? 'bg-blue-50/60 border-blue-200' : 'bg-white border-slate-200 hover:bg-slate-50'}`}
                >
                  <div className="flex items-center gap-3">
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedCenterIds([...selectedCenterIds, center.id]);
                        } else {
                          setSelectedCenterIds(selectedCenterIds.filter(id => id !== center.id));
                        }
                      }}
                      className="w-4 h-4 text-blue-600 rounded-md focus:ring-blue-500"
                    />
                    <div>
                      <div className="font-bold text-slate-900">{center.name}</div>
                      <div className="text-[10px] text-slate-500">{center.zone?.name} &bull; Capacity: {center.capacity}</div>
                    </div>
                  </div>
                  <span className="font-mono text-[10px] font-bold text-slate-400">{center.code}</span>
                </label>
              );
            })}
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsCentersModalOpen(false)}
              className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleSaveCenters}
              className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
            >
              Save Center Allotment
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

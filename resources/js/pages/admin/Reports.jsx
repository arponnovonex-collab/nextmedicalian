import React, { useState, useEffect } from 'react';
import api, { downloadFile } from '../../api/axios';
import {
  FileSpreadsheet,
  Download,
  Users,
  Building,
  CheckCircle2,
  CalendarCheck,
  KeyRound
} from 'lucide-react';

export default function Reports() {
  const [exams, setExams] = useState([]);
  const [centers, setCenters] = useState([]);
  const [selectedExamId, setSelectedExamId] = useState('');
  const [selectedCenterId, setSelectedCenterId] = useState('');

  useEffect(() => {
    fetchExams();
    fetchCenters();
  }, []);

  const fetchExams = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success && res.data.data.data.length > 0) {
        setExams(res.data.data.data);
        setSelectedExamId(res.data.data.data[0].id);
      }
    } catch (e) {}
  };

  const fetchCenters = async () => {
    try {
      const res = await api.get('/admin/centers');
      if (res.data.success) setCenters(res.data.data);
    } catch (e) {}
  };

  const handleDownloadAttendance = () => {
    let url = `/admin/reports/attendance?exam_id=${selectedExamId}`;
    if (selectedCenterId) url += `&center_id=${selectedCenterId}`;
    downloadFile(url, `attendance_sheet_exam_${selectedExamId}.pdf`);
  };

  const handleDownloadSeatPlan = () => {
    let url = `/admin/reports/seat-plan?exam_id=${selectedExamId}`;
    if (selectedCenterId) url += `&center_id=${selectedCenterId}`;
    downloadFile(url, `seat_plan_exam_${selectedExamId}.pdf`);
  };

  const handleDownloadEvaluatorsReport = () => {
    downloadFile(`/admin/reports/evaluators?exam_id=${selectedExamId}`, `evaluators_report_exam_${selectedExamId}.pdf`);
  };

  const handleDownloadCredentialsSms = () => {
    let url = `/admin/students/export-credentials?exam_id=${selectedExamId}`;
    if (selectedCenterId) url += `&center_id=${selectedCenterId}`;
    downloadFile(url, `student_sms_credentials_exam_${selectedExamId}.csv`);
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Examination Reports & Printable Sheets</h1>
        <p className="text-xs text-slate-500 mt-1">Export official attendance sheets, physical hall seat plans, and faculty evaluation logs.</p>
      </div>

      {/* Scope Selector */}
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">Filter Scope</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-[11px] font-bold text-slate-500 uppercase mb-1">Target Examination *</label>
            <select
              value={selectedExamId}
              onChange={(e) => setSelectedExamId(e.target.value)}
              className="w-full px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl bg-white"
            >
              {exams.map(e => (
                <option key={e.id} value={e.id}>{e.code} - {e.title}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-bold text-slate-500 uppercase mb-1">Center Filter (Optional)</label>
            <select
              value={selectedCenterId}
              onChange={(e) => setSelectedCenterId(e.target.value)}
              className="w-full px-3 py-2 text-xs font-semibold border border-slate-200 rounded-xl bg-white"
            >
              <option value="">All Examination Centers</option>
              {centers.map(c => (
                <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Report Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div>
            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
              <KeyRound className="w-5 h-5" />
            </div>
            <h3 className="text-sm font-bold text-slate-900">Student SMS Credentials (CSV)</h3>
            <p className="text-xs text-slate-500 mt-1">
              Export Candidate Reg ID, 4-Digit Password, Mobile Number, Center, and ready-made SMS broadcast text.
            </p>
          </div>
          <button
            onClick={handleDownloadCredentialsSms}
            disabled={!selectedExamId}
            className="mt-6 w-full inline-flex items-center justify-center gap-2 py-2 px-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer"
          >
            <Download className="w-4 h-4" /> Export SMS List (.CSV)
          </button>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div>
            <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
              <Users className="w-5 h-5" />
            </div>
            <h3 className="text-sm font-bold text-slate-900">Official Attendance Sheet</h3>
            <p className="text-xs text-slate-500 mt-1">
              Printable candidate roster with designated physical signature and present/absent bubbling blocks.
            </p>
          </div>
          <button
            onClick={handleDownloadAttendance}
            disabled={!selectedExamId}
            className="mt-6 w-full inline-flex items-center justify-center gap-2 py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer"
          >
            <Download className="w-4 h-4" /> Export CSV / Sheet
          </button>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div>
            <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
              <Building className="w-5 h-5" />
            </div>
            <h3 className="text-sm font-bold text-slate-900">Room-Wise Seat Plan</h3>
            <p className="text-xs text-slate-500 mt-1">
              Hall entrance pasteable seat plan indexed by Center, Floor, Room Number, and Seat Number.
            </p>
          </div>
          <button
            onClick={handleDownloadSeatPlan}
            disabled={!selectedExamId}
            className="mt-6 w-full inline-flex items-center justify-center gap-2 py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer"
          >
            <Download className="w-4 h-4" /> Export CSV / Plan
          </button>
        </div>

        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div>
            <div className="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3">
              <FileSpreadsheet className="w-5 h-5" />
            </div>
            <h3 className="text-sm font-bold text-slate-900">Faculty Evaluation Log</h3>
            <p className="text-xs text-slate-500 mt-1">
              Comprehensive report of which faculty evaluator graded each physical script, marks awarded, and timestamps.
            </p>
          </div>
          <button
            onClick={handleDownloadEvaluatorsReport}
            disabled={!selectedExamId}
            className="mt-6 w-full inline-flex items-center justify-center gap-2 py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer"
          >
            <Download className="w-4 h-4" /> Export Evaluators Log
          </button>
        </div>
      </div>
    </div>
  );
}

import React, { useState, useEffect } from 'react';
import api, { downloadFile } from '../../api/axios';
import {
  Download,
  CalendarCheck,
  Clock,
  MapPin,
  ExternalLink,
  Award,
  AlertCircle,
  CheckCircle2,
  FileText,
  ShieldCheck,
  Building,
  User,
  Sparkles
} from 'lucide-react';

export default function StudentDashboard() {
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedExamIndex, setSelectedExamIndex] = useState(0);
  const [countdown, setCountdown] = useState({ days: 0, hours: 0, minutes: 0, seconds: 0 });

  useEffect(() => {
    fetchStudentDashboard();
  }, []);

  const fetchStudentDashboard = async () => {
    setLoading(true);
    try {
      const res = await api.get('/student/dashboard');
      if (res.data.success) {
        setDashboardData(res.data.data);
      }
    } catch (e) {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const currentExam = dashboardData?.enrolled_exams?.[selectedExamIndex];

  // Countdown timer effect
  useEffect(() => {
    if (!currentExam || !currentExam.countdown_seconds) return;

    let remaining = Math.max(0, Math.floor(Number(currentExam.countdown_seconds) || 0));

    const updateTimer = (rem) => {
      const days = Math.floor(rem / 86400);
      const hours = Math.floor((rem % 86400) / 3600);
      const minutes = Math.floor((rem % 3600) / 60);
      const seconds = Math.floor(rem % 60);
      setCountdown({
        days: Math.floor(days),
        hours: Math.floor(hours),
        minutes: Math.floor(minutes),
        seconds: Math.floor(seconds),
      });
    };

    updateTimer(remaining);

    const interval = setInterval(() => {
      if (remaining <= 0) {
        clearInterval(interval);
        return;
      }
      remaining = Math.max(0, Math.floor(remaining - 1));
      updateTimer(remaining);
    }, 1000);

    return () => clearInterval(interval);
  }, [currentExam]);

  const handleDownloadAdmit = () => {
    if (!currentExam) return;
    downloadFile(`/student/exams/${currentExam.exam_id}/admit-card`, `admit_card_${currentExam.exam_code}.pdf`);
  };

  const handleDownloadResultPdf = () => {
    if (!currentExam) return;
    downloadFile(`/student/exams/${currentExam.exam_id}/result-pdf`, `result_${currentExam.exam_code}.pdf`);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <div className="w-8 h-8 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin"></div>
      </div>
    );
  }

  const student = dashboardData?.student || {};
  const exams = dashboardData?.enrolled_exams || [];

  return (
    <div className="space-y-6">
      {/* Student Welcome Card */}
      <div className="bg-slate-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl relative overflow-hidden">
        <div className="absolute top-0 right-0 w-80 h-80 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-950/80 border border-blue-500/30 rounded-full text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-3">
              <Sparkles className="w-3 h-3" /> Candidate Portal
            </div>
            <h1 className="text-2xl sm:text-3xl font-black tracking-tight">{student.name}</h1>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-300 font-mono mt-1">
              <span>Registration No: <strong className="text-blue-300">{student.registration_number}</strong></span>
              <span>Mobile: <strong className="text-slate-100">{student.phone}</strong></span>
            </div>
          </div>

          <div className="bg-white/10 backdrop-blur-md px-4 py-3 rounded-2xl border border-white/10 text-xs">
            <div className="text-[10px] text-slate-400 uppercase font-bold">Institution</div>
            <div className="font-semibold text-white">{student.institution || 'Medical Candidate'}</div>
            <div className="text-[10px] text-blue-300 font-bold mt-0.5">{student.hsc_batch || 'HSC Batch'}</div>
          </div>
        </div>
      </div>

      {exams.length === 0 ? (
        <div className="bg-white p-12 text-center rounded-2xl border border-slate-200">
          <CalendarCheck className="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <h3 className="text-base font-bold text-slate-800">No Enrolled Examinations</h3>
          <p className="text-xs text-slate-500 mt-1">You are currently not enrolled in any examinations. Please contact your center administrator.</p>
        </div>
      ) : (
        <>
          {/* Multi-Exam Switcher */}
          {exams.length > 1 && (
            <div className="flex gap-2 overflow-x-auto pb-1">
              {exams.map((ex, idx) => (
                <button
                  key={ex.exam_id}
                  onClick={() => setSelectedExamIndex(idx)}
                  className={`px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer ${selectedExamIndex === idx ? 'bg-blue-600 text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'}`}
                >
                  {ex.exam_code} &bull; {ex.exam_title}
                </button>
              ))}
            </div>
          )}

          {currentExam && (
            <div className="space-y-6">
              {/* Countdown Banner */}
              {currentExam.is_upcoming && (
                <div className="bg-gradient-to-r from-blue-900 to-indigo-900 text-white p-5 rounded-2xl shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div>
                    <span className="text-[10px] font-bold uppercase tracking-widest text-blue-300 block mb-0.5">Physical Examination Schedule</span>
                    <h3 className="text-lg font-bold">{currentExam.exam_title}</h3>
                    <div className="text-xs text-slate-300 flex items-center gap-2 mt-1">
                      <Clock className="w-3.5 h-3.5" /> {currentExam.exam_date_formatted} &bull; {currentExam.start_time} - {currentExam.end_time}
                    </div>
                  </div>

                  {/* Countdown Box */}
                  <div className="grid grid-cols-4 gap-2 text-center">
                    <div className="bg-white/10 px-3 py-1.5 rounded-xl border border-white/10">
                      <div className="text-lg font-black">{Math.floor(countdown.days)}</div>
                      <div className="text-[9px] uppercase font-bold text-blue-300">Days</div>
                    </div>
                    <div className="bg-white/10 px-3 py-1.5 rounded-xl border border-white/10">
                      <div className="text-lg font-black">{Math.floor(countdown.hours)}</div>
                      <div className="text-[9px] uppercase font-bold text-blue-300">Hours</div>
                    </div>
                    <div className="bg-white/10 px-3 py-1.5 rounded-xl border border-white/10">
                      <div className="text-lg font-black">{Math.floor(countdown.minutes)}</div>
                      <div className="text-[9px] uppercase font-bold text-blue-300">Mins</div>
                    </div>
                    <div className="bg-white/10 px-3 py-1.5 rounded-xl border border-white/10">
                      <div className="text-lg font-black">{Math.floor(countdown.seconds)}</div>
                      <div className="text-[9px] uppercase font-bold text-blue-300">Secs</div>
                    </div>
                  </div>
                </div>
              )}

              {/* Physical Center Allocation & Google Maps */}
              <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div className="p-5 border-b border-slate-100 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <MapPin className="w-5 h-5 text-blue-600" />
                    <h3 className="text-sm font-bold text-slate-900">Physical Examination Center & Seating</h3>
                  </div>

                  {currentExam.center?.google_maps_url && (
                    <a
                      href={currentExam.center.google_maps_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 rounded-xl text-xs font-bold hover:bg-blue-100 transition-colors"
                    >
                      Open in Google Maps <ExternalLink className="w-3.5 h-3.5" />
                    </a>
                  )}
                </div>

                <div className="p-5 space-y-4">
                  {currentExam.center ? (
                    <div className="space-y-4">
                      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="col-span-1 sm:col-span-2">
                          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Assigned Center</span>
                          <h4 className="text-base font-extrabold text-slate-900">{currentExam.center.name}</h4>
                          <p className="text-xs text-slate-500 mt-0.5">{currentExam.center.address}</p>
                          <span className="inline-block mt-2 text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                            Zone: {currentExam.center.zone}
                          </span>
                        </div>

                        <div className="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-100 text-center">
                          <div>
                            <span className="text-[10px] font-bold text-slate-400 uppercase block">Room</span>
                            <span className="text-lg font-black text-blue-600">{currentExam.room_number}</span>
                          </div>
                          <div>
                            <span className="text-[10px] font-bold text-slate-400 uppercase block">Seat</span>
                            <span className="text-lg font-black text-slate-900">{currentExam.seat_number}</span>
                          </div>
                        </div>
                      </div>

                      {/* Google Maps Interactive Location Embed */}
                      {(currentExam.center.google_maps_embed_url || currentExam.center.name) && (
                        <div className="rounded-2xl overflow-hidden border border-slate-200 h-56 bg-slate-100 shadow-inner">
                          <iframe
                            title="Center Location Map"
                            src={
                              currentExam.center.google_maps_embed_url ||
                              `https://maps.google.com/maps?q=${encodeURIComponent(
                                currentExam.center.name + ', ' + (currentExam.center.address || '')
                              )}&t=&z=15&ie=UTF8&iwloc=&output=embed`
                            }
                            width="100%"
                            height="100%"
                            style={{ border: 0 }}
                            allowFullScreen=""
                            loading="lazy"
                          ></iframe>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="py-6 text-center text-xs text-slate-400">
                      Center allocation is currently being finalized by the administration.
                    </div>
                  )}
                </div>
              </div>

              {/* Admit Card & Result Cards Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {/* Admit Card Card */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                  <div>
                    <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                      <FileText className="w-5 h-5" />
                    </div>
                    <h3 className="text-sm font-bold text-slate-900">Official Admit Card (PDF)</h3>
                    <p className="text-xs text-slate-500 mt-1">
                      Contains your candidate photo, center address, room number, seat allocation, and authentic verification QR code.
                    </p>
                  </div>

                  <div className="mt-5 pt-4 border-t border-slate-100">
                    {currentExam.is_admit_available ? (
                      <button
                        onClick={handleDownloadAdmit}
                        className="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
                      >
                        <Download className="w-4 h-4" /> Download Physical Admit Card
                      </button>
                    ) : (
                      <div className="text-xs text-amber-700 bg-amber-50 p-2.5 rounded-xl border border-amber-200 font-semibold flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 shrink-0" />
                        <span>Admit Card available: {currentExam.admit_publish_date || 'Soon'}</span>
                      </div>
                    )}
                  </div>
                </div>

                {/* Published Result Card */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                  <div>
                    <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                      <Award className="w-5 h-5" />
                    </div>
                    <h3 className="text-sm font-bold text-slate-900">Examination Results & Rank</h3>
                    <p className="text-xs text-slate-500 mt-1">
                      View your obtained score, overall merit position, zone-wise ranking, and download official verified result sheet.
                    </p>
                  </div>

                  <div className="mt-5 pt-4 border-t border-slate-100">
                    {currentExam.is_result_published ? (
                      <button
                        onClick={handleDownloadResultPdf}
                        className="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
                      >
                        <Download className="w-4 h-4" /> Download Official Result Sheet (PDF)
                      </button>
                    ) : (
                      <div className="text-xs text-slate-500 bg-slate-50 p-2.5 rounded-xl border border-slate-200 font-semibold flex items-center gap-2">
                        <Clock className="w-4 h-4 text-slate-400 shrink-0" />
                        <span>Result Not Published Yet</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

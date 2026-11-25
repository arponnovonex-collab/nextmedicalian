import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import Modal from '../../components/Modal';
import {
  MapPin,
  Building,
  Plus,
  Search,
  ExternalLink,
  DoorOpen,
  Edit,
  Trash2,
  Phone,
  User,
  Layers
} from 'lucide-react';

export default function CentersManagement() {
  const [activeTab, setActiveTab] = useState('centers'); // 'centers' or 'zones'

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'zones') {
      setActiveTab('zones');
    }
  }, []);
  const [centers, setCenters] = useState([]);
  const [zones, setZones] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedZoneFilter, setSelectedZoneFilter] = useState('');

  // Modals
  const [isCenterModalOpen, setIsCenterModalOpen] = useState(false);
  const [editingCenter, setEditingCenter] = useState(null);
  const [isZoneModalOpen, setIsZoneModalOpen] = useState(false);
  const [editingZone, setEditingZone] = useState(null);
  const [isRoomsModalOpen, setIsRoomsModalOpen] = useState(false);
  const [activeCenterForRooms, setActiveCenterForRooms] = useState(null);
  const [centerRooms, setCenterRooms] = useState([]);

  // Form states
  const [centerForm, setCenterForm] = useState({
    zone_id: '',
    name: '',
    code: '',
    address: '',
    google_maps_url: '',
    capacity: 200,
    contact_person: '',
    contact_phone: '',
    special_instructions: '',
  });

  const [zoneForm, setZoneForm] = useState({
    name: '',
    code: '',
  });

  const [roomForm, setRoomForm] = useState({
    room_number: '',
    floor: '1st Floor',
    capacity: 40,
  });

  useEffect(() => {
    fetchZones();
    fetchCenters();
  }, [search, selectedZoneFilter]);

  const fetchZones = async () => {
    try {
      const res = await api.get('/admin/zones');
      if (res.data.success) {
        setZones(res.data.data);
      }
    } catch (e) {
      // ignore
    }
  };

  const fetchCenters = async () => {
    setLoading(true);
    try {
      let url = '/admin/centers?';
      if (search) url += `search=${encodeURIComponent(search)}&`;
      if (selectedZoneFilter) url += `zone_id=${selectedZoneFilter}&`;
      const res = await api.get(url);
      if (res.data.success) {
        setCenters(res.data.data);
      }
    } catch (e) {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  // Center Handlers
  const handleOpenCreateCenter = () => {
    setEditingCenter(null);
    setCenterForm({
      zone_id: zones[0]?.id || '',
      name: '',
      code: '',
      address: '',
      google_maps_url: '',
      capacity: 200,
      contact_person: '',
      contact_phone: '',
      special_instructions: '',
    });
    setIsCenterModalOpen(true);
  };

  const handleOpenEditCenter = (c) => {
    setEditingCenter(c);
    setCenterForm({
      zone_id: c.zone_id,
      name: c.name,
      code: c.code,
      address: c.address,
      google_maps_url: c.google_maps_url || '',
      capacity: c.capacity,
      contact_person: c.contact_person || '',
      contact_phone: c.contact_phone || '',
      special_instructions: c.special_instructions || '',
    });
    setIsCenterModalOpen(true);
  };

  const handleSaveCenter = async (e) => {
    e.preventDefault();
    try {
      if (editingCenter) {
        await api.put(`/admin/centers/${editingCenter.id}`, centerForm);
      } else {
        await api.post('/admin/centers', centerForm);
      }
      setIsCenterModalOpen(false);
      fetchCenters();
    } catch (err) {
      alert(err.response?.data?.message || 'Error saving center.');
    }
  };

  const handleDeleteCenter = async (center) => {
    if (!confirm(`Are you sure you want to delete examination center "${center.name}" (${center.code})?`)) {
      return;
    }

    try {
      const res = await api.delete(`/admin/centers/${center.id}`);
      if (res.data.success) {
        alert(res.data.message || 'Examination center deleted successfully.');
        fetchCenters();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error deleting examination center.');
    }
  };

  // Zone Handlers
  const handleOpenCreateZone = () => {
    setEditingZone(null);
    setZoneForm({ name: '', code: '' });
    setIsZoneModalOpen(true);
  };

  const handleSaveZone = async (e) => {
    e.preventDefault();
    try {
      if (editingZone) {
        await api.put(`/admin/zones/${editingZone.id}`, zoneForm);
      } else {
        await api.post('/admin/zones', zoneForm);
      }
      setIsZoneModalOpen(false);
      fetchZones();
    } catch (err) {
      alert(err.response?.data?.message || 'Error saving zone.');
    }
  };

  const handleEditZone = (zone) => {
    setEditingZone(zone);
    setZoneForm({ name: zone.name, code: zone.code });
    setIsZoneModalOpen(true);
  };

  const handleDeleteZone = async (zone, force = false) => {
    if (!force && !confirm(`Are you sure you want to delete zone "${zone.name}" (${zone.code})?`)) {
      return;
    }

    try {
      const url = force ? `/admin/zones/${zone.id}?force=true` : `/admin/zones/${zone.id}`;
      const res = await api.delete(url);
      if (res.data.success) {
        alert(res.data.message || 'Zone deleted successfully.');
        fetchZones();
      }
    } catch (err) {
      if (err.response?.data?.has_centers) {
        if (confirm(`${err.response.data.message}\n\nDo you want to FORCE DELETE this zone along with its ${err.response.data.centers_count} examination center(s)?`)) {
          handleDeleteZone(zone, true);
          return;
        }
      } else {
        alert(err.response?.data?.message || 'Error deleting zone.');
      }
    }
  };

  // Room Handlers
  const handleOpenRooms = async (center) => {
    setActiveCenterForRooms(center);
    try {
      const res = await api.get(`/admin/centers/${center.id}/rooms`);
      if (res.data.success) {
        setCenterRooms(res.data.data);
      }
    } catch (e) {
      setCenterRooms([]);
    }
    setIsRoomsModalOpen(true);
  };

  const handleAddRoom = async (e) => {
    e.preventDefault();
    if (!activeCenterForRooms) return;
    try {
      await api.post(`/admin/centers/${activeCenterForRooms.id}/rooms`, roomForm);
      setRoomForm({ room_number: '', floor: '1st Floor', capacity: 40 });
      const res = await api.get(`/admin/centers/${activeCenterForRooms.id}/rooms`);
      if (res.data.success) setCenterRooms(res.data.data);
      fetchCenters();
    } catch (err) {
      alert(err.response?.data?.message || 'Error adding room.');
    }
  };

  const handleDeleteRoom = async (roomId) => {
    if (!confirm('Are you sure you want to delete this room?')) return;
    try {
      await api.delete(`/admin/centers/${activeCenterForRooms.id}/rooms/${roomId}`);
      setCenterRooms(prev => prev.filter(r => r.id !== roomId));
      fetchCenters();
    } catch (err) {
      alert(err.response?.data?.message || 'Cannot delete room.');
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Geographic Examination Setup</h1>
          <p className="text-xs text-slate-500 mt-1">Manage geographic zones, offline test centers, rooms, and Google Maps coordinates.</p>
        </div>

        <div className="flex items-center gap-2">
          {activeTab === 'centers' ? (
            <button
              onClick={handleOpenCreateCenter}
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
            >
              <Plus className="w-4 h-4" /> Add Test Center
            </button>
          ) : (
            <button
              onClick={handleOpenCreateZone}
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
            >
              <Plus className="w-4 h-4" /> Add New Zone
            </button>
          )}
        </div>
      </div>

      {/* Segmented Tabs Bar */}
      <div className="flex flex-wrap items-center justify-between gap-3 p-1.5 bg-slate-100 rounded-2xl border border-slate-200/80">
        <div className="flex items-center gap-1.5">
          <button
            type="button"
            onClick={() => setActiveTab('centers')}
            className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'centers'
                ? 'bg-white text-blue-700 shadow-xs border border-slate-200/60'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
            }`}
          >
            <Building className="w-4 h-4 text-blue-600" />
            <span>Examination Centers</span>
            <span className="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
              {centers.length}
            </span>
          </button>

          <button
            type="button"
            onClick={() => setActiveTab('zones')}
            className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              activeTab === 'zones'
                ? 'bg-white text-blue-700 shadow-xs border border-slate-200/60'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
            }`}
          >
            <MapPin className="w-4 h-4 text-emerald-600" />
            <span>Administrative Zones</span>
            <span className="text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">
              {zones.length}
            </span>
          </button>
        </div>

        {activeTab === 'centers' && (
          <button
            type="button"
            onClick={() => setActiveTab('zones')}
            className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-slate-700 hover:text-blue-700 hover:bg-white rounded-xl transition-all cursor-pointer"
          >
            <Layers className="w-3.5 h-3.5 text-slate-500" />
            Switch to Zones &rarr;
          </button>
        )}
      </div>

      {activeTab === 'centers' ? (
        <div className="space-y-4">
          {/* Filters */}
          <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div className="relative w-full sm:w-80">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
              <input
                type="text"
                placeholder="Search center by name, code, address..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              />
            </div>

            <div className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <select
                value={selectedZoneFilter}
                onChange={(e) => setSelectedZoneFilter(e.target.value)}
                className="px-3 py-2 text-xs font-semibold text-slate-700 border border-slate-200 rounded-xl focus:outline-hidden bg-white w-full sm:w-auto"
              >
                <option value="">All Zones ({zones.length})</option>
                {zones.map(z => (
                  <option key={z.id} value={z.id}>{z.name} ({z.code})</option>
                ))}
              </select>

              <button
                type="button"
                onClick={() => setActiveTab('zones')}
                className="whitespace-nowrap inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition-colors cursor-pointer"
                title="Manage and Delete Zones"
              >
                <Trash2 className="w-3.5 h-3.5 text-rose-600" />
                Manage / Delete Zones
              </button>
            </div>
          </div>

          {/* Centers Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {loading ? (
              <div className="col-span-full py-12 text-center text-xs text-slate-400">Loading centers...</div>
            ) : centers.length === 0 ? (
              <div className="col-span-full py-12 text-center text-xs text-slate-400">No examination centers found.</div>
            ) : (
              centers.map(center => (
                <div key={center.id} className="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between hover:border-blue-300 transition-all">
                  <div>
                    <div className="flex items-start justify-between gap-2 mb-2">
                      <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                          {center.zone?.name}
                        </span>
                        <h3 className="text-base font-bold text-slate-900 mt-1">{center.name}</h3>
                      </div>
                      <span className="font-mono text-xs font-bold text-slate-400">{center.code}</span>
                    </div>

                    <p className="text-xs text-slate-500 mb-4 line-clamp-2">{center.address}</p>

                    <div className="grid grid-cols-2 gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100 mb-4">
                      <div>
                        <span className="text-[10px] uppercase font-bold text-slate-400 block">Total Capacity</span>
                        <span className="font-black text-slate-800">{center.capacity} Students</span>
                      </div>
                      <div>
                        <span className="text-[10px] uppercase font-bold text-slate-400 block">Rooms Configured</span>
                        <span className="font-black text-slate-800">{center.rooms_count || 0} Rooms</span>
                      </div>
                    </div>

                    {center.contact_person && (
                      <div className="text-[11px] text-slate-600 space-y-1 mb-4">
                        <div className="flex items-center gap-1.5">
                          <User className="w-3.5 h-3.5 text-slate-400" /> {center.contact_person}
                        </div>
                        {center.contact_phone && (
                          <div className="flex items-center gap-1.5">
                            <Phone className="w-3.5 h-3.5 text-slate-400" /> {center.contact_phone}
                          </div>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <a
                      href={center.google_maps_url || `https://maps.google.com/?q=${encodeURIComponent(center.name + ', ' + center.address)}`}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-800 transition-colors"
                    >
                      <MapPin className="w-3.5 h-3.5" /> View on Maps <ExternalLink className="w-3 h-3" />
                    </a>

                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => handleOpenRooms(center)}
                        title="Manage Center Rooms"
                        className="p-1.5 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                      >
                        <DoorOpen className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleOpenEditCenter(center)}
                        title="Edit Center"
                        className="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDeleteCenter(center)}
                        title="Delete Center"
                        className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      ) : (
        /* Zones Table */
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-sm font-bold text-slate-900">Administrative Zones List</h2>
              <p className="text-xs text-slate-500">Edit zone details or delete administrative zones.</p>
            </div>
            <button
              onClick={handleOpenCreateZone}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors cursor-pointer"
            >
              <Plus className="w-4 h-4" /> Add New Zone
            </button>
          </div>

          <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden max-w-4xl">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                  <th className="py-3 px-4">Zone Name</th>
                  <th className="py-3 px-4">Zone Code</th>
                  <th className="py-3 px-4">Centers Count</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {zones.length === 0 ? (
                  <tr>
                    <td colSpan="4" className="py-8 text-center text-slate-400">
                      No administrative zones defined. Click "Add New Zone" to create one.
                    </td>
                  </tr>
                ) : (
                  zones.map(zone => (
                    <tr key={zone.id} className="hover:bg-slate-50/70 transition-colors">
                      <td className="py-3 px-4 font-bold text-slate-900 text-sm">
                        <div className="flex items-center gap-2">
                          <MapPin className="w-4 h-4 text-blue-600" />
                          <span>{zone.name}</span>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <span className="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">
                          {zone.code}
                        </span>
                      </td>
                      <td className="py-3 px-4 font-semibold text-slate-600">
                        {zone.centers_count || 0} Test Centers
                      </td>
                      <td className="py-3 px-4 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            type="button"
                            onClick={() => handleEditZone(zone)}
                            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer"
                            title="Edit Zone"
                          >
                            <Edit className="w-3.5 h-3.5 text-slate-500" />
                            Edit
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDeleteZone(zone)}
                            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition-colors cursor-pointer"
                            title="Delete Zone"
                          >
                            <Trash2 className="w-3.5 h-3.5 text-rose-600" />
                            Delete Zone
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
      )}

      {/* Add / Edit Center Modal */}
      <Modal
        isOpen={isCenterModalOpen}
        onClose={() => setIsCenterModalOpen(false)}
        title={editingCenter ? 'Edit Examination Center' : 'Create Examination Center'}
        maxWidth="max-w-xl"
      >
        <form onSubmit={handleSaveCenter} className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Zone *
              </label>
              <select
                required
                value={centerForm.zone_id}
                onChange={(e) => setCenterForm({ ...centerForm, zone_id: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600"
              >
                {zones.map(z => (
                  <option key={z.id} value={z.id}>{z.name} ({z.code})</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Center Code * (e.g. DHK-03)
              </label>
              <input
                type="text"
                required
                value={centerForm.code}
                onChange={(e) => setCenterForm({ ...centerForm, code: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
                placeholder="DHK-03"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Center Name *
            </label>
            <input
              type="text"
              required
              value={centerForm.name}
              onChange={(e) => setCenterForm({ ...centerForm, name: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              placeholder="e.g. Dhaka City College"
            />
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Full Physical Address *
            </label>
            <textarea
              rows={2}
              required
              value={centerForm.address}
              onChange={(e) => setCenterForm({ ...centerForm, address: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              placeholder="Dhanmondi, Dhaka-1205"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Google Maps Share URL
              </label>
              <input
                type="url"
                value={centerForm.google_maps_url}
                onChange={(e) => setCenterForm({ ...centerForm, google_maps_url: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
                placeholder="https://maps.app.goo.gl/..."
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Total Seating Capacity *
              </label>
              <input
                type="number"
                required
                value={centerForm.capacity}
                onChange={(e) => setCenterForm({ ...centerForm, capacity: parseInt(e.target.value) })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Contact Person
              </label>
              <input
                type="text"
                value={centerForm.contact_person}
                onChange={(e) => setCenterForm({ ...centerForm, contact_person: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Contact Phone
              </label>
              <input
                type="text"
                value={centerForm.contact_phone}
                onChange={(e) => setCenterForm({ ...centerForm, contact_phone: e.target.value })}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>
          </div>

          <div className="flex items-center justify-between pt-3 border-t border-slate-100">
            {editingCenter ? (
              <button
                type="button"
                onClick={() => {
                  setIsCenterModalOpen(false);
                  handleDeleteCenter(editingCenter);
                }}
                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-xl transition-colors cursor-pointer"
              >
                <Trash2 className="w-3.5 h-3.5" /> Delete Center
              </button>
            ) : <div />}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setIsCenterModalOpen(false)}
                className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700 cursor-pointer"
              >
                {editingCenter ? 'Update Center' : 'Save Center'}
              </button>
            </div>
          </div>
        </form>
      </Modal>

      {/* Add / Edit Zone Modal */}
      <Modal
        isOpen={isZoneModalOpen}
        onClose={() => setIsZoneModalOpen(false)}
        title={editingZone ? 'Edit Administrative Zone' : 'Add Administrative Zone'}
      >
        <form onSubmit={handleSaveZone} className="space-y-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Zone Name *
            </label>
            <input
              type="text"
              required
              value={zoneForm.name}
              onChange={(e) => setZoneForm({ ...zoneForm, name: e.target.value })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              placeholder="e.g. Gazipur"
            />
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Zone Code *
            </label>
            <input
              type="text"
              required
              value={zoneForm.code}
              onChange={(e) => setZoneForm({ ...zoneForm, code: e.target.value.toUpperCase() })}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              placeholder="GZP"
            />
          </div>

          <div className="flex items-center justify-between pt-3 border-t border-slate-100">
            {editingZone ? (
              <button
                type="button"
                onClick={() => {
                  setIsZoneModalOpen(false);
                  handleDeleteZone(editingZone);
                }}
                className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-xl transition-colors cursor-pointer"
              >
                <Trash2 className="w-3.5 h-3.5" /> Delete Zone
              </button>
            ) : <div />}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setIsZoneModalOpen(false)}
                className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700 cursor-pointer"
              >
                {editingZone ? 'Update Zone' : 'Save Zone'}
              </button>
            </div>
          </div>
        </form>
      </Modal>

      {/* Room Configuration Sub-Modal */}
      <Modal
        isOpen={isRoomsModalOpen}
        onClose={() => setIsRoomsModalOpen(false)}
        title={`Rooms Setup - ${activeCenterForRooms?.name}`}
        maxWidth="max-w-xl"
      >
        <div className="space-y-5">
          {/* Add Room Form */}
          <form onSubmit={handleAddRoom} className="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
            <span className="text-xs font-extrabold uppercase tracking-wider text-slate-700">Add Room to Center</span>
            <div className="grid grid-cols-3 gap-2">
              <input
                type="text"
                required
                placeholder="Room No (e.g. 201)"
                value={roomForm.room_number}
                onChange={(e) => setRoomForm({ ...roomForm, room_number: e.target.value })}
                className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
              />
              <input
                type="text"
                placeholder="Floor (e.g. 2nd Floor)"
                value={roomForm.floor}
                onChange={(e) => setRoomForm({ ...roomForm, floor: e.target.value })}
                className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
              />
              <input
                type="number"
                required
                placeholder="Capacity"
                value={roomForm.capacity}
                onChange={(e) => setRoomForm({ ...roomForm, capacity: parseInt(e.target.value) })}
                className="px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg"
              />
            </div>
            <button
              type="submit"
              className="w-full py-1.5 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-slate-800"
            >
              Add Room
            </button>
          </form>

          {/* Existing Rooms List */}
          <div className="space-y-2 max-h-60 overflow-y-auto">
            {centerRooms.length === 0 ? (
              <div className="text-center py-6 text-xs text-slate-400">No rooms added yet.</div>
            ) : (
              centerRooms.map(r => (
                <div key={r.id} className="flex items-center justify-between p-2.5 bg-white border border-slate-200 rounded-xl text-xs">
                  <div>
                    <span className="font-bold text-slate-900">{r.room_number}</span>
                    <span className="text-slate-500 ml-2">({r.floor || 'Ground'}) &bull; Capacity: <strong>{r.capacity}</strong></span>
                  </div>
                  <button
                    onClick={() => handleDeleteRoom(r.id)}
                    className="p-1 text-slate-400 hover:text-rose-600 rounded-md"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              ))
            )}
          </div>
        </div>
      </Modal>
    </div>
  );
}

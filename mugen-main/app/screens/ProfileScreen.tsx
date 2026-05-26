import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator, Animated as RNAnimated, Image, StyleSheet, View, Text, ScrollView,
  TouchableOpacity, StatusBar, Platform,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useColors } from '../context/ThemeContext';
import { getStorageUrl, userApi } from '../../services/api';
import { storage } from '../../services/storage';

const ACCENT = '#FF0066';
const DAY_LABELS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
const HORIZONTAL_MARGIN = 16;

type Stats = { total_workouts: number; total_hours: number; streak: number; points: number };
type WeekDay = { day: string; mins: number };

function computeAchievements(stats: Stats) {
  return [
    { icon: '🏆', label: 'Primer reto',  unlocked: stats.total_workouts >= 1 },
    { icon: '🔥', label: 'Racha 7d',     unlocked: stats.streak >= 7 },
    { icon: '⚡', label: '100 sesiones', unlocked: stats.total_workouts >= 100 },
    { icon: '🥊', label: 'Activo 10',    unlocked: stats.total_workouts >= 10 },
    { icon: '🏅', label: 'Top 10',       unlocked: false },
    { icon: '💪', label: 'Élite',        unlocked: stats.total_workouts >= 50 },
  ];
}

function getRank(workouts: number) {
  if (workouts >= 100) return 'Guerrero Elite';
  if (workouts >= 50)  return 'Campeón';
  if (workouts >= 20)  return 'Guerrero';
  if (workouts >= 10)  return 'Iniciado';
  return 'Novato';
}

function getLevel(workouts: number) {
  return Math.max(1, Math.floor(workouts / 5) + 1);
}

const cardShadow = Platform.select({
  ios: { shadowColor: '#000000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.08, shadowRadius: 3 },
  android: { elevation: 2 },
  default: {},
});

export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const navigation = useNavigation<any>();
  const { C } = useColors();

  const [userName, setUserName]   = useState('Usuario');
  const [avatarUri, setAvatarUri] = useState<string | null>(null);
  const [stats, setStats]         = useState<Stats>({ total_workouts: 0, total_hours: 0, streak: 0, points: 0 });
  const [weekly, setWeekly]       = useState<WeekDay[]>(DAY_LABELS.map(d => ({ day: d, mins: 0 })));
  const [loading, setLoading]     = useState(true);

  const loadProfile = useCallback(async () => {
    const [t, userRaw] = await Promise.all([storage.get('token'), storage.get('user')]);
    if (userRaw) {
      try {
        const u = JSON.parse(userRaw);
        setUserName(u.name ?? 'Usuario');
        setAvatarUri(getStorageUrl(u.avatar));
      } catch (_) {}
    }
    if (t) {
      try {
        const [statsRes, weeklyRes] = await Promise.all([
          userApi.stats(t),
          userApi.weekly(t),
        ]);
        setStats(statsRes.data);
        if (weeklyRes.data.weekly?.length) setWeekly(weeklyRes.data.weekly);
      } catch (_) {}
    }
    setLoading(false);
  }, []);

  useFocusEffect(useCallback(() => {
    setLoading(true);
    loadProfile();
  }, [loadProfile]));

  const maxMins      = Math.max(1, ...weekly.map(d => d.mins));
  const achievements = computeAchievements(stats);
  const level        = getLevel(stats.total_workouts);
  const xpPercent    = Math.min(100, (stats.total_workouts % 5) * 20);
  const rank         = getRank(stats.total_workouts);

  const todayIndex = new Date().getDay() === 0 ? 6 : new Date().getDay() - 1;
  const avgMins = weekly.length ? Math.round(weekly.reduce((s, d) => s + d.mins, 0) / 7) : 0;

  return (
    <View style={[s.root, { backgroundColor: C.bg }]}>
      <StatusBar barStyle={C.statusBar} backgroundColor="transparent" translucent />

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={s.scroll}>

        {/* HERO */}
        <View style={[s.hero, { backgroundColor: C.card, paddingTop: insets.top + 8 }]}>
          <View style={s.topBar}>
            <Text style={[s.screenTitle, { color: C.textPrimary }]}>Mi Perfil</Text>
            <TouchableOpacity onPress={() => navigation.navigate('screens/SettingsScreen')} style={[s.iconBtn, { backgroundColor: C.card, borderColor: C.border }]}>
              <Ionicons name="settings-outline" size={22} color={C.textPrimary} />
            </TouchableOpacity>
          </View>

          <View style={s.avatarSection}>
            <TouchableOpacity onPress={() => navigation.navigate('screens/EditProfileScreen')} activeOpacity={0.85}>
              <View style={[s.avatarRing, { borderColor: C.mugenPink + '55' }]}>
                {avatarUri ? (
                  <Image source={{ uri: avatarUri }} style={s.avatarImg} />
                ) : (
                  <View style={[s.avatarImg, { backgroundColor: C.elevated }]}>
                    <Ionicons name="person" size={40} color={C.mugenPink} />
                  </View>
                )}
              </View>
              <View style={[s.editBtn, { backgroundColor: C.mugenPink }]}>
                <Ionicons name="pencil" size={12} color="#FFF" />
              </View>
            </TouchableOpacity>
          </View>

          <Text style={[s.userName, { color: C.textPrimary }]}>{userName}</Text>
          <View style={[s.rankBadge, { backgroundColor: C.mugenPink + '20' }]}>
            <Ionicons name="shield-checkmark" size={14} color={C.mugenPink} />
            <Text style={[s.rankBadgeText, { color: C.mugenPink }]}>{rank} · Nv.{level}</Text>
          </View>

          <View style={s.xpSection}>
            <View style={s.xpLabelRow}>
              <Text style={[s.xpLabel, { color: C.textSecondary }]}>XP hacia nivel {level + 1}</Text>
              <Text style={[s.xpPct, { color: C.mugenPink }]}>{xpPercent}%</Text>
            </View>
            <View style={[s.xpBar, { backgroundColor: C.elevated }]}>
              <LinearGradient
                colors={[C.mugenPink, '#FF6B35']}
                start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }}
                style={[s.xpFill, { width: `${xpPercent}%` }]}
              />
            </View>
          </View>
        </View>

        {/* STATS */}
        {loading ? (
          <ActivityIndicator color={C.mugenPink} style={{ marginTop: 24 }} />
        ) : (
          <View style={s.statsRow}>
            <View style={[s.statCard, cardShadow, { backgroundColor: C.card }]}>
              <Ionicons name="barbell-outline" size={22} color={ACCENT} />
              <Text style={[s.statVal, { color: C.textPrimary }]}>{stats.total_workouts}</Text>
              <Text style={[s.statLabel, { color: C.textSecondary }]}>ENTRENOS</Text>
            </View>
            <View style={[s.statCard, cardShadow, { backgroundColor: C.card }]}>
              <Ionicons name="time-outline" size={22} color={ACCENT} />
              <Text style={[s.statVal, { color: C.textPrimary }]}>{stats.total_hours}</Text>
              <Text style={[s.statLabel, { color: C.textSecondary }]}>HORAS</Text>
            </View>
            <View style={[s.statCard, cardShadow, { backgroundColor: C.card }]}>
              <Ionicons name="flame-outline" size={22} color={ACCENT} />
              <Text style={[s.statVal, { color: C.textPrimary }]}>{stats.streak}</Text>
              <Text style={[s.statLabel, { color: C.textSecondary }]}>RACHA</Text>
            </View>
          </View>
        )}

        {/* CHART */}
        <View style={s.chartCard}>
          <Text style={[s.sectionLabel, { color: C.textSecondary }]}>ACTIVIDAD SEMANAL</Text>
          <View style={s.barChart}>
            {weekly.map((item, i) => {
              const h       = Math.max(4, (item.mins / maxMins) * 90);
              const isToday = i === todayIndex;
              return (
                <View key={item.day} style={s.barCol}>
                  <Text style={[s.barMins, { color: C.textMuted }]}>{item.mins > 0 ? item.mins : ''}</Text>
                  <View style={[s.barTrack, { backgroundColor: C.elevated }]}>
                    <LinearGradient
                      colors={[C.mugenPink, C.mugenPinkDark]}
                      style={[s.bar, { height: h }]}
                      borderRadius={6}
                      opacity={isToday ? 1 : 0.45}
                    />
                  </View>
                  <Text style={[s.barLabel, { color: isToday ? C.mugenPink : C.textSecondary }]}>{item.day}</Text>
                </View>
              );
            })}
          </View>
          <Text style={[s.chartAvg, { color: C.textMuted }]}>Promedio: {avgMins} min/día</Text>
        </View>

        {/* LOGROS */}
        <View style={s.section}>
          <View style={s.sectionHeader}>
            <Text style={[s.sectionLabel, { color: C.textSecondary }]}>LOGROS</Text>
            <Text style={[s.seeAll, { color: C.mugenPink }]}>
              {achievements.filter(a => a.unlocked).length}/{achievements.length}
            </Text>
          </View>
          <View style={s.grid}>
            {achievements.map(a => (
              <View key={a.label} style={[s.badge, { backgroundColor: C.card, borderColor: a.unlocked ? C.mugenPink + '35' : C.border, opacity: a.unlocked ? 1 : 0.4 }, cardShadow]}>
                <Text style={s.badgeIcon}>{a.icon}</Text>
                <Text style={[s.badgeLabel, { color: C.textSecondary }]}>{a.label}</Text>
              </View>
            ))}
          </View>
        </View>

        <View style={{ height: 110 }} />
      </ScrollView>
    </View>
  );
}

const s = StyleSheet.create({
  root:          { flex: 1 },
  scroll:        { paddingBottom: 24 },
  hero:          { paddingBottom: 24, alignItems: 'center', borderBottomLeftRadius: 24, borderBottomRightRadius: 24 },
  topBar:        { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', width: '100%', paddingHorizontal: HORIZONTAL_MARGIN, marginBottom: 20 },
  screenTitle:   { fontSize: 22, fontWeight: '900' },
  iconBtn:       { width: 40, height: 40, borderRadius: 12, justifyContent: 'center', alignItems: 'center', borderWidth: 1 },
  avatarSection: { position: 'relative', marginBottom: 14 },
  avatarRing:    { width: 100, height: 100, borderRadius: 50, justifyContent: 'center', alignItems: 'center', borderWidth: 2 },
  avatarImg:     { width: 90, height: 90, borderRadius: 45, justifyContent: 'center', alignItems: 'center' },
  editBtn:       { position: 'absolute', bottom: 2, right: 0, width: 26, height: 26, borderRadius: 13, justifyContent: 'center', alignItems: 'center' },
  userName:      { fontSize: 24, fontWeight: '900', marginBottom: 8 },
  rankBadge:     { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 14, paddingVertical: 6, borderRadius: 10, gap: 6, marginBottom: 18 },
  rankBadgeText: { fontWeight: '800', fontSize: 12 },
  xpSection:     { width: '100%', paddingHorizontal: HORIZONTAL_MARGIN },
  xpLabelRow:    { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  xpLabel:       { fontSize: 11, fontWeight: '600' },
  xpPct:         { fontSize: 11, fontWeight: '800' },
  xpBar:         { height: 6, borderRadius: 4, overflow: 'hidden' },
  xpFill:        { height: '100%', borderRadius: 4 },
  statsRow:      { flexDirection: 'row', paddingHorizontal: HORIZONTAL_MARGIN, marginTop: 20, gap: 10 },
  statCard:      { flex: 1, borderRadius: 12, padding: 14, alignItems: 'center', gap: 5 },
  statVal:       { fontSize: 22, fontWeight: '900' },
  statLabel:     { fontSize: 9, letterSpacing: 1, textTransform: 'uppercase' },
  chartCard:     { marginHorizontal: HORIZONTAL_MARGIN, marginTop: 16, borderRadius: 12, padding: 20 },
  sectionLabel:  { fontSize: 11, letterSpacing: 1.5, textTransform: 'uppercase', marginBottom: 16 },
  chartAvg:      { fontSize: 11, marginTop: 12, textAlign: 'center' },
  barChart:      { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', height: 130 },
  barCol:        { flex: 1, alignItems: 'center', gap: 4 },
  barMins:       { fontSize: 9, fontWeight: '600' },
  barTrack:      { height: 90, justifyContent: 'flex-end', borderRadius: 6, width: 24, alignItems: 'center' },
  bar:           { width: 20, borderRadius: 6, minHeight: 4 },
  barLabel:      { fontSize: 11, fontWeight: '800' },
  section:       { marginHorizontal: HORIZONTAL_MARGIN, marginTop: 20 },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  seeAll:        { fontSize: 13, fontWeight: '700' },
  grid:          { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 14 },
  badge:         { width: '30.5%', aspectRatio: 1, borderRadius: 12, justifyContent: 'center', alignItems: 'center', borderWidth: 1, gap: 6 },
  badgeIcon:     { fontSize: 26 },
  badgeLabel:    { fontSize: 10, fontWeight: '800', textAlign: 'center' },
});

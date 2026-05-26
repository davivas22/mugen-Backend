import React, { useState } from 'react';
import {
  StyleSheet, View, Text, FlatList, TouchableOpacity,
  Image, Platform, StatusBar,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useColors } from '../context/ThemeContext';

const FILTERS = ['Semanal', 'Mensual', 'Histórico'] as const;
type Filter = typeof FILTERS[number];

interface User { id: string; name: string; points: number; rank: number; avatar?: string; isMe?: boolean }

const TOP3: User[] = [
  { id: '2', name: 'María G.',  points: 15420, rank: 2, avatar: 'https://i.pravatar.cc/150?u=2' },
  { id: '1', name: 'Juan K.',   points: 18900, rank: 1, avatar: 'https://i.pravatar.cc/150?u=1' },
  { id: '3', name: 'Elena R.',  points: 14200, rank: 3, avatar: 'https://i.pravatar.cc/150?u=3' },
];
const LIST: User[] = Array.from({ length: 20 }, (_, i) => ({
  id: `${i + 4}`, name: `Atleta ${i + 4}`, points: 12000 - i * 350, rank: i + 4,
  avatar: `https://i.pravatar.cc/150?u=${i + 4}`,
}));

const PODIUM_COLORS = ['#C0C0C0', '#FFD700', '#CD7F32'];

export default function RankingScreen() {
  const [filter, setFilter] = useState<Filter>('Semanal');
  const { C } = useColors();

  const Podium = ({ user, idx }: { user: User; idx: number }) => {
    const isWinner = idx === 1;
    return (
      <TouchableOpacity style={[s.podiumItem, isWinner && s.podiumWinner]} activeOpacity={0.8}>
        {isWinner && <MaterialCommunityIcons name="crown" size={28} color={C.gold} style={s.crown} />}
        <View style={[s.podiumRing, { borderColor: PODIUM_COLORS[idx], width: isWinner ? 88 : 72, height: isWinner ? 88 : 72, borderRadius: isWinner ? 44 : 36 }]}>
          <Image source={{ uri: user.avatar }} style={{ flex: 1, borderRadius: isWinner ? 40 : 32 }} />
        </View>
        <View style={[s.rankBadge, { backgroundColor: PODIUM_COLORS[idx] }]}>
          <Text style={s.rankBadgeText}>{user.rank}</Text>
        </View>
        <Text style={[s.podiumName, { color: C.textPrimary }]} numberOfLines={1}>{user.name}</Text>
        <Text style={[s.podiumPts, { color: PODIUM_COLORS[idx] }]}>{user.points.toLocaleString()}</Text>
      </TouchableOpacity>
    );
  };

  return (
    <View style={[s.root, { backgroundColor: C.bg }]}>
      <StatusBar barStyle={C.statusBar} backgroundColor="transparent" translucent />

      <FlatList
        data={LIST}
        keyExtractor={item => item.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={s.list}
        ListHeaderComponent={
          <>
            <LinearGradient colors={[C.mugenPink + '28', 'transparent']} style={s.headerGrad}>
              <View style={{ paddingTop: Platform.OS === 'android' ? (StatusBar.currentHeight ?? 0) + 12 : 56, paddingHorizontal: 24 }}>
                <Text style={[s.headerTitle, { color: C.textPrimary }]}>Ranking Global</Text>
                <Text style={[s.headerSub, { color: C.textSecondary }]}>Compite, supera, domina</Text>
              </View>
            </LinearGradient>

            <View style={[s.tabBar, { backgroundColor: C.card, borderColor: C.border }]}>
              {FILTERS.map(f => (
                <TouchableOpacity key={f} onPress={() => setFilter(f)} style={[s.tab, filter === f && s.tabActive]} activeOpacity={0.7}>
                  <Text style={[s.tabText, { color: filter === f ? '#FFF' : C.textSecondary }]}>{f}</Text>
                </TouchableOpacity>
              ))}
            </View>

            <View style={s.podiumRow}>
              {[TOP3[0], TOP3[1], TOP3[2]].map((u, i) => <Podium key={u.id} user={u} idx={i} />)}
            </View>

            <Text style={[s.listLabel, { color: C.textPrimary }]}>Clasificación</Text>
          </>
        }
        renderItem={({ item }) => (
          <TouchableOpacity style={[s.rankRow, { backgroundColor: C.card, borderColor: item.isMe ? C.mugenPink + '60' : C.border }]} activeOpacity={0.75}>
            <Text style={[s.rowRank, { color: item.isMe ? C.mugenPink : C.textSecondary }]}>#{item.rank}</Text>
            <Image source={{ uri: item.avatar }} style={[s.rowAvatar, { backgroundColor: C.elevated }]} />
            <Text style={[s.rowName, { color: item.isMe ? C.mugenPink : C.textPrimary }]} numberOfLines={1}>{item.name}</Text>
            <Text style={[s.rowPts, { color: item.isMe ? C.mugenPink : C.textSecondary }]}>{item.points.toLocaleString()} pts</Text>
            <MaterialCommunityIcons name="chevron-right" size={16} color={C.textMuted} />
          </TouchableOpacity>
        )}
        ListFooterComponent={<View style={{ height: 100 }} />}
      />

      {/* STICKY MY POSITION */}
      <View style={s.myBar}>
        <LinearGradient colors={[C.mugenPink, C.mugenPinkDark]} style={StyleSheet.absoluteFill} borderRadius={20} />
        <View style={s.myBarLeft}>
          <View style={s.myAvatar}>
            <MaterialCommunityIcons name="account" size={18} color={C.mugenPink} />
          </View>
          <View>
            <Text style={s.myBarLabel}>Tu posición</Text>
            <Text style={s.myBarRank}>#42</Text>
          </View>
        </View>
        <View style={s.myBarRight}>
          <Text style={s.myBarPts}>1,250 pts</Text>
          <MaterialCommunityIcons name="trending-up" size={18} color="rgba(255,255,255,0.8)" />
        </View>
      </View>
    </View>
  );
}

const s = StyleSheet.create({
  root:         { flex: 1 },
  list:         { paddingBottom: 20 },
  headerGrad:   { paddingBottom: 20 },
  headerTitle:  { fontSize: 32, fontWeight: '900', letterSpacing: -1 },
  headerSub:    { fontSize: 14, marginTop: 2 },
  tabBar:       { flexDirection: 'row', marginHorizontal: 20, marginTop: 12, borderRadius: 16, padding: 4, gap: 4, borderWidth: 1 },
  tab:          { flex: 1, paddingVertical: 10, alignItems: 'center', borderRadius: 12 },
  tabActive:    { backgroundColor: '#FF2E63' },
  tabText:      { fontSize: 13, fontWeight: '700' },
  podiumRow:    { flexDirection: 'row', justifyContent: 'center', alignItems: 'flex-end', paddingHorizontal: 16, marginTop: 28, marginBottom: 32, gap: 8 },
  podiumItem:   { flex: 1, alignItems: 'center', gap: 8 },
  podiumWinner: { transform: [{ translateY: -16 }] },
  crown:        { marginBottom: -4 },
  podiumRing:   { borderWidth: 2.5, padding: 3, justifyContent: 'center', alignItems: 'center' },
  rankBadge:    { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 8, marginTop: -4 },
  rankBadgeText:{ color: '#0D0D14', fontSize: 12, fontWeight: '900' },
  podiumName:   { fontSize: 13, fontWeight: '800', textAlign: 'center' },
  podiumPts:    { fontSize: 13, fontWeight: '900' },
  listLabel:    { fontSize: 18, fontWeight: '900', marginHorizontal: 20, marginBottom: 12 },
  rankRow:      { flexDirection: 'row', alignItems: 'center', marginHorizontal: 20, marginBottom: 8, padding: 12, borderRadius: 16, borderWidth: 1, gap: 10 },
  rowRank:      { width: 36, fontSize: 13, fontWeight: '900', textAlign: 'center' },
  rowAvatar:    { width: 38, height: 38, borderRadius: 19 },
  rowName:      { flex: 1, fontSize: 14, fontWeight: '700' },
  rowPts:       { fontSize: 13, fontWeight: '900' },
  myBar:        { position: 'absolute', bottom: 90, left: 20, right: 20, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, borderRadius: 20, overflow: 'hidden', shadowColor: '#FF2E63', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.4, shadowRadius: 16, elevation: 10 },
  myBarLeft:    { flexDirection: 'row', alignItems: 'center', gap: 12 },
  myAvatar:     { width: 36, height: 36, borderRadius: 18, backgroundColor: '#FFF', justifyContent: 'center', alignItems: 'center' },
  myBarLabel:   { fontSize: 11, color: 'rgba(255,255,255,0.75)', fontWeight: '600' },
  myBarRank:    { fontSize: 18, fontWeight: '900', color: '#FFF' },
  myBarRight:   { flexDirection: 'row', alignItems: 'center', gap: 8 },
  myBarPts:     { fontSize: 18, fontWeight: '900', color: '#FFF' },
});
